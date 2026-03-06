/* TNT Marine Listings - Frontend JS
   Created by Cox Group */

(function($) {
    'use strict';

    /* =========================================================
       GALLERY
       ========================================================= */
    var galleryImages = [];
    var currentIndex  = 0;

    function initGallery() {
        var $thumbs = $('.tnt-gallery-thumbs img');
        if ( ! $thumbs.length ) return;

        $thumbs.each(function() {
            galleryImages.push( $(this).data('full') );
        });

        $thumbs.on('click', function() {
            var idx = parseInt( $(this).data('index'), 10 );
            setGalleryImage( idx );
        });

        $('.tnt-prev').on('click', function() {
            var idx = ( currentIndex - 1 + galleryImages.length ) % galleryImages.length;
            setGalleryImage( idx );
        });

        $('.tnt-next').on('click', function() {
            var idx = ( currentIndex + 1 ) % galleryImages.length;
            setGalleryImage( idx );
        });

        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if ( e.key === 'ArrowLeft' ) {
                var idx = ( currentIndex - 1 + galleryImages.length ) % galleryImages.length;
                setGalleryImage( idx );
            } else if ( e.key === 'ArrowRight' ) {
                var idx2 = ( currentIndex + 1 ) % galleryImages.length;
                setGalleryImage( idx2 );
            }
        });
    }

    function setGalleryImage( idx ) {
        currentIndex = idx;
        var $main = $('#tnt-main-photo');
        $main.css('opacity', 0);
        setTimeout(function() {
            $main.attr('src', galleryImages[ idx ]);
            $main.css('opacity', 1);
        }, 150);
        $('.tnt-gallery-thumbs img').removeClass('active');
        $('.tnt-gallery-thumbs img[data-index="' + idx + '"]').addClass('active');
    }

    /* =========================================================
       ACCORDION
       ========================================================= */
    function initAccordion() {
        $(document).on('click', '.tnt-accordion-trigger', function() {
            var $item   = $(this).closest('.tnt-accordion-item');
            var target  = $(this).data('target');
            var $body   = $('#' + target);
            var isOpen  = $item.hasClass('tnt-open');

            $(this).attr('aria-expanded', isOpen ? 'false' : 'true');
            $item.toggleClass('tnt-open', ! isOpen);
            $body.slideToggle(220);
        });
    }

    /* =========================================================
       INQUIRY FORM
       ========================================================= */
    function initInquiryForm() {
        $('#tnt-inquiry-submit').on('click', function() {
            var $btn     = $(this);
            var name     = $.trim( $('#tnt_name').val() );
            var email    = $.trim( $('#tnt_email').val() );
            var message  = $.trim( $('#tnt_message').val() );
            var phone    = $.trim( $('#tnt_phone').val() );
            var listingId = $('#tnt_listing_id').val();
            var $notice  = $('#tnt-inquiry-message');

            // Basic validation
            if ( ! name || ! email || ! message ) {
                showNotice( $notice, 'error', 'Please fill in your name, email, and message.' );
                return;
            }

            if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
                showNotice( $notice, 'error', 'Please enter a valid email address.' );
                return;
            }

            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url:  tntMarine.ajax_url,
                type: 'POST',
                data: {
                    action:     'tnt_marine_inquiry',
                    nonce:      tntMarine.nonce,
                    name:       name,
                    email:      email,
                    phone:      phone,
                    message:    message,
                    listing_id: listingId,
                },
                success: function( res ) {
                    if ( res.success ) {
                        showNotice( $notice, 'success', res.data.message );
                        $('#tnt_name').val('');
                        $('#tnt_email').val('');
                        $('#tnt_phone').val('');
                        $('#tnt_message').val('');
                        $btn.hide();
                    } else {
                        showNotice( $notice, 'error', res.data.message );
                        $btn.prop('disabled', false).text('Send Inquiry');
                    }
                },
                error: function() {
                    showNotice( $notice, 'error', 'An error occurred. Please try again or contact us directly.' );
                    $btn.prop('disabled', false).text('Send Inquiry');
                }
            });
        });
    }

    function showNotice( $el, type, msg ) {
        $el
            .removeClass('success error')
            .addClass( type )
            .text( msg )
            .show();
        $('html, body').animate({ scrollTop: $el.offset().top - 80 }, 300);
    }

    /* =========================================================
       INIT
       ========================================================= */
    $(document).ready(function() {
        initGallery();
        initAccordion();
        initInquiryForm();
    });

})(jQuery);
