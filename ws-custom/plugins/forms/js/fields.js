/**
 * Form fields (vanilla JS, no jQuery).
 *
 * The eye toggle: a visual button that stands for a hidden checkbox.
 *
 *   <input type="checkbox" name="is_name_public" style="display:none">
 *   <button type="button"
 *           data-icon-toggle="'visibility', 'Public' : 'visibility_off', 'Hidden'">
 *     visibility_off
 *   </button>
 *
 * The CHECKBOX is the source of truth — it is what the form submits — and the
 * button is only its face. So the button never decides the state from its own
 * icon: it flips the checkbox and then draws whatever the checkbox says.
 *
 * Which checkbox? In this order: the one named in `data-for`, the one named
 * like the button (`name`), or the one in the same field wrapper. The last
 * rule is what makes it work by proximity, without an attribute someone has
 * to remember. It used to require a `name` on the button, and the seven
 * buttons of the profile form had none: the icon flipped, the checkbox did
 * not, and "save" quietly dropped the organization.
 */
document.addEventListener('DOMContentLoaded', () => {

    /** Strings inside single quotes: "'a', 'b'" → ['a', 'b'] */
    const quoted = (str) => [...str.matchAll(/'([^']+)'/g)].map((m) => m[1]);

    /** The checkbox a toggle button stands for, or null. */
    const checkboxFor = (button) => {
        const name = button.dataset.for || button.getAttribute('name');
        if (name) {
            const byName = document.querySelector(`input[type="checkbox"][name="${name}"]`);
            if (byName) return byName;
        }
        const wrapper = button.closest('.input, label, p, fieldset');
        return wrapper ? wrapper.querySelector('input[type="checkbox"]') : null;
    };

    document.querySelectorAll('[data-icon-toggle]').forEach((button) => {
        const parts = button.getAttribute('data-icon-toggle').split(':').map((s) => s.trim());
        if (parts.length !== 2) {
            console.error('WS CMS: invalid data-icon-toggle format on', button);
            return;
        }
        const [iconOn, titleOn] = quoted(parts[0]);
        const [iconOff, titleOff] = quoted(parts[1]);
        const checkbox = checkboxFor(button);
        if (!checkbox) {
            console.warn('WS CMS: no checkbox found for toggle', button);
        }

        const draw = (on) => {
            button.textContent = on ? iconOn : iconOff;
            button.title = on ? titleOn : titleOff;
            button.classList.toggle('is-active', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        };

        // On load the face follows the checkbox, not the other way round: the
        // server renders both, and if they ever disagree the checkbox wins.
        if (checkbox) draw(checkbox.checked);

        button.addEventListener('click', (e) => {
            e.preventDefault(); // inside a <label>: no double toggle, no submit
            let on;
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                on = checkbox.checked;
            } else {
                on = button.textContent.trim() !== iconOn; // no checkbox: purely visual
            }
            draw(on);
        });
    });
});
