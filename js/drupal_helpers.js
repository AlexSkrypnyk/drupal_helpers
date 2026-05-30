/**
 * @file
 * Drupal Helpers behaviors.
 */

/**
 * @param {Object} Drupal  The Drupal object.
 */
((Drupal) => {
  Drupal.behaviors.yourExtension = {
    formatLoadTime(date) {
      return `Page loaded: ${date.toLocaleString()}`;
    },
    attach(context) {
      const elements = context.querySelectorAll(
        '[data-drupal_helpers-time]:not(.drupal_helpers-processed)',
      );

      elements.forEach((element) => {
        element.classList.add('drupal_helpers-processed');
        element.textContent = this.formatLoadTime(new Date());
      });
    },
  };
})(Drupal);
