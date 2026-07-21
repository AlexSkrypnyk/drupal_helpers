<?php

declare(strict_types=1);

namespace Drupal\drupal_helpers\Report;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Accumulates operation results for a single deploy hook run.
 *
 * One shared instance is injected into every helper, so all operations
 * performed during a run contribute to the same tally. Each recorded operation
 * bumps a per-status counter, is logged to the dedicated 'drupal_helpers'
 * channel, and is surfaced through the Messenger, which renders it for both CLI
 * (drush) and web (update.php) contexts. The tally is returned as a deploy
 * hook's output string via Helper::report().
 *
 * The counting status (created, updated, ...) and the message severity (status,
 * warning, error) are independent: a "skipped" operation whose target was
 * missing can still surface as a warning while counting under 'skipped'.
 */
class Reporter {

  public const CREATED = 'created';

  public const UPDATED = 'updated';

  public const DELETED = 'deleted';

  public const SKIPPED = 'skipped';

  public const PROCESSED = 'processed';

  public const FAILED = 'failed';

  public const SEVERITY_STATUS = 'status';

  public const SEVERITY_WARNING = 'warning';

  public const SEVERITY_ERROR = 'error';

  /**
   * Known statuses in summary display order, mapped to their summary labels.
   */
  protected const LABELS = [
    self::CREATED => 'created',
    self::UPDATED => 'updated',
    self::DELETED => 'deleted',
    self::SKIPPED => 'skipped',
    self::PROCESSED => 'processed',
    self::FAILED => 'failed',
  ];

  /**
   * Number of operations recorded per status, keyed by status.
   *
   * @var array<string, int>
   */
  protected array $counts = [];

  /**
   * Recorded messages in order, each as ['status' => ..., 'message' => ...].
   *
   * @var array<int, array{status: string, message: string}>
   */
  protected array $messages = [];

  public function __construct(
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Record a created operation.
   *
   * @param string|\Stringable $message
   *   Message describing the operation.
   * @param int $count
   *   Number of operations this record represents.
   */
  public function created(string|\Stringable $message, int $count = 1): void {
    $this->record(self::CREATED, $message, $count);
  }

  /**
   * Record an updated operation.
   *
   * @param string|\Stringable $message
   *   Message describing the operation.
   * @param int $count
   *   Number of operations this record represents.
   */
  public function updated(string|\Stringable $message, int $count = 1): void {
    $this->record(self::UPDATED, $message, $count);
  }

  /**
   * Record a deleted operation.
   *
   * @param string|\Stringable $message
   *   Message describing the operation.
   * @param int $count
   *   Number of operations this record represents.
   */
  public function deleted(string|\Stringable $message, int $count = 1): void {
    $this->record(self::DELETED, $message, $count);
  }

  /**
   * Record a skipped operation.
   *
   * @param string|\Stringable $message
   *   Message describing the operation.
   * @param int $count
   *   Number of operations this record represents.
   * @param string|null $severity
   *   Message severity, defaulting to a status message. Pass SEVERITY_WARNING
   *   for a skip that warrants operator attention, such as a missing target.
   */
  public function skipped(string|\Stringable $message, int $count = 1, ?string $severity = NULL): void {
    $this->record(self::SKIPPED, $message, $count, $severity);
  }

  /**
   * Record a failed operation as a message that does not abort the run.
   *
   * @param string|\Stringable $message
   *   Message describing the failure.
   * @param int $count
   *   Number of failures this record represents.
   * @param string|null $severity
   *   Message severity, defaulting to a warning. Pass SEVERITY_ERROR for a
   *   failure that should surface as an error.
   */
  public function failed(string|\Stringable $message, int $count = 1, ?string $severity = NULL): void {
    $this->record(self::FAILED, $message, $count, $severity);
  }

  /**
   * Record an operation under a status, logging and surfacing its message.
   *
   * @param string $status
   *   One of the status constants, or a custom status string.
   * @param string|\Stringable $message
   *   Message describing the operation.
   * @param int $count
   *   Number of operations this record represents.
   * @param string|null $severity
   *   Message severity, or NULL to derive it from the status (failures warn,
   *   everything else is a status message).
   */
  public function record(string $status, string|\Stringable $message, int $count = 1, ?string $severity = NULL): void {
    if ($count < 1) {
      return;
    }

    $this->counts[$status] = ($this->counts[$status] ?? 0) + $count;
    $this->messages[] = ['status' => $status, 'message' => (string) $message];

    $severity ??= $status === self::FAILED ? self::SEVERITY_WARNING : self::SEVERITY_STATUS;
    $this->surface($message, $severity);
  }

  /**
   * Log and surface a message without counting it towards the tally.
   *
   * Used for progress lines, such as a batch completion notice, that are not an
   * operation in their own right.
   *
   * @param string|\Stringable $message
   *   Message to surface.
   */
  public function message(string|\Stringable $message): void {
    $this->surface($message, self::SEVERITY_STATUS);
  }

  /**
   * Build a single-line summary of the recorded counts.
   *
   * Only non-zero statuses are listed, known statuses first in display order,
   * then any custom statuses in insertion order. The result is a single line so
   * it renders correctly whether returned to drush or shown on update.php.
   *
   * @return string
   *   A summary such as "Created 12, skipped 3.", or "No changes." when nothing
   *   was recorded.
   */
  public function summary(): string {
    $segments = [];

    foreach (self::LABELS as $status => $label) {
      if (($this->counts[$status] ?? 0) > 0) {
        $segments[] = $label . ' ' . $this->counts[$status];
      }
    }

    foreach ($this->counts as $status => $count) {
      if (!isset(self::LABELS[$status]) && $count > 0) {
        $segments[] = $status . ' ' . $count;
      }
    }

    if ($segments === []) {
      return 'No changes.';
    }

    return ucfirst(implode(', ', $segments)) . '.';
  }

  /**
   * Get the number of operations recorded under a status.
   *
   * @param string $status
   *   The status to read.
   *
   * @return int
   *   The recorded count, or 0 when nothing was recorded for the status.
   */
  public function count(string $status): int {
    return $this->counts[$status] ?? 0;
  }

  /**
   * Get every recorded message in order.
   *
   * @return array<int, array{status: string, message: string}>
   *   The recorded messages, each with its 'status' and 'message'.
   */
  public function messages(): array {
    return $this->messages;
  }

  /**
   * Clear the recorded counts and messages.
   */
  public function reset(): void {
    $this->counts = [];
    $this->messages = [];
  }

  /**
   * Log a message and add it to the Messenger at the given severity.
   *
   * The message is passed to the logger as an opaque placeholder value so that
   * any '@', '%' or '{}' characters it contains are never treated as log
   * placeholders.
   *
   * @param string|\Stringable $message
   *   The message to surface.
   * @param string $severity
   *   One of the SEVERITY_* constants.
   */
  protected function surface(string|\Stringable $message, string $severity): void {
    $text = (string) $message;
    // Markup is preserved so the messenger renders it safely; any other
    // Stringable is flattened to a plain string it can accept.
    $display = $message instanceof MarkupInterface ? $message : $text;

    if ($severity === self::SEVERITY_ERROR) {
      $this->logger->error('@message', ['@message' => $text]);
      $this->messenger->addError($display);

      return;
    }

    if ($severity === self::SEVERITY_WARNING) {
      $this->logger->warning('@message', ['@message' => $text]);
      $this->messenger->addWarning($display);

      return;
    }

    $this->logger->info('@message', ['@message' => $text]);
    $this->messenger->addStatus($display);
  }

}
