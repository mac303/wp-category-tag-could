/**
 * Wrapper function to safely use $
 */
function wpctcAdminWrapper($) {
    var wpctcAdmin = {

        /**
         * Main entry point
         */
        init: function () {
            $('.cloud-type-selector').change(wpctcAdmin.hideshow);
            $('.cloud-type-selector').each(wpctcAdmin.hideshow);
            $('.wpctc-color-picker').each(function () {
                wpctcAdmin.farbalize($(this));
            });
            $(document).on('widget-updated', wpctcAdmin.on_form_update);
            $(document).on('widget-added', wpctcAdmin.on_form_update);
        },
        hideshow: function () {
            if ($(this).val() == 'array') {
                $(this).parent().parent().find('.cloud-non-price').show();
                $(this).parent().parent().find('.canvas-config').show();
            }
            else if ($(this).val() == 'price') {
                $(this).parent().parent().find('.canvas-config').hide();
                $(this).parent().parent().find('.cloud-non-price').hide();
            }
            else {
                $(this).parent().parent().find('.cloud-non-price').show();
                $(this).parent().parent().find('.canvas-config').hide();
            }
        },
        farbalize: function (widget_el) {
            var id = widget_el.attr('rel');
            var farb = widget_el.farbtastic(function (color) {
                var fb = this;
                // Set background/foreground color
                $('#' + id).css({
                    backgroundColor: fb.color,
                    color: fb.hsl[2] > 0.5 ? '#000' : '#fff'
                });

                // Change linked value
                $('#' + id).each(function () {
                    if (!this.value || this.value != fb.color) {
                        this.value = fb.color;
                    }
                });
            });
            var input = $('#' + id);
            var f = function () {
                farb.get(0).farbtastic.setColor(input.get(0).value);
            }
            input.unbind('keyup', f);
            input.bind('keyup', f);
            if (input.get(0).value) {
                farb.get(0).farbtastic.setColor(input.get(0).value);
            }
        },
        on_form_update: function (e, widget_el) {
            widget_el.find('.cloud-type-selector').change(wpctcAdmin.hideshow);
            widget_el.find('.cloud-type-selector').each(wpctcAdmin.hideshow);
            widget_el.find('.wpctc-color-picker').each(function () {
                wpctcAdmin.farbalize($(this));
            });
        }
    }; // end wpctcAdmin

    $(document).ready(wpctcAdmin.init);

} // end wpctcAdminWrapper()

wpctcAdminWrapper(jQuery);
