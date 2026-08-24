/**
 * Theme functions file (Vanilla JS - no jQuery)
 */

(function () {
  var body = document.body;
    /**
     * isotype_toggle — Vanilla JS version
     */
    function isotype_toggle(slug) {
      var togglers = document.querySelectorAll('.' + slug + '-toggle');

      togglers.forEach(function (toggler) {
        var toggleGroup = toggler.getAttribute('data-toggle-group');
        var inSameGroup = toggleGroup
          ? document.querySelectorAll('[data-toggle-group="' + toggleGroup + '"]')
          : [];

        toggler.addEventListener('click', function (event) {
          var wrapper = document.querySelector('.' + slug + '-box-wrapper');

          toggler.classList.toggle('active');

          // Close siblings in the same toggle group
          inSameGroup.forEach(function (sibling) {
            if (
              !sibling.classList.contains(slug + '-toggle') &&
              sibling.classList.contains('active')
            ) {
              sibling.classList.toggle('active');
              // Extract the sibling slug from the first class name
              var firstClass = sibling.className.split(/\s+/)[0];
              var siblingSlug = firstClass.replace('-toggle', '');
              var siblingWrapper = document.querySelector('.' + siblingSlug + '-box-wrapper');
              if (siblingWrapper) {
                siblingWrapper.classList.add('hide');
              }
            }
          });

          if (wrapper) {
            wrapper.classList.toggle('hide');

            if (
              toggler.classList.contains('active') ||
              document.querySelector('.' + slug + '-toggle') === event.target
            ) {
              var field = wrapper.querySelector('.' + slug + '-field');
              if (field) field.focus();
            }
          }
        });
      });
    }

    // --- Initialize Togglers ---
    isotype_toggle('follow');
    isotype_toggle('search');
    isotype_toggle('languages');
    isotype_toggle('login');
    isotype_toggle('menu');
})();