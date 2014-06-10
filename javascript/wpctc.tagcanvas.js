/**
 * Created by benohead on 09.06.14.
 */


/**
 * Wrapper function to safely use $
 */
function wpctcWrapper($) {
    var wpctc = {
        /**
         * Main entry point
         */
        init: function () {
            wpctc.prefix = 'wpctc_';
            wpctc.templateURL = $('#template-url').val();
            wpctc.ajaxPostURL = $('#ajax-post-url').val();
            wpctc.registerEventHandlers();
            $('.tagcloud-canvas').each(function() {
                $(this).tagcanvas({
                    outlineThickness: 0,
                    textFont: null,
                    textColour: null,
                    maxSpeed : 0.06,
                    minBrightness : 0.1,
                    depth : 0.95,
                    pulsateTo : 0.2,
                    pulsateTime : 0.75,
                    decel : 0.9,
                    reverse : true,
                    fadeIn : 800,
                    weight: true
                }, $(this).attr('id')+'_tags');
            });
        },
        /**
         * Registers event handlers
         */
        registerEventHandlers: function () {
        }
    }; // end wpctc
    $(document).ready(wpctc.init);
} // end wpctcWrapper()
wpctcWrapper(jQuery);
