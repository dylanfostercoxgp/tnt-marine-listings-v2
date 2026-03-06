/* TNT Marine Listings - Admin JS
   Created by Cox Group
   v1.0.4 */

(function($) {
    'use strict';

    /* =========================================================
       GALLERY UPLOADER
       ========================================================= */
    var mediaFrame;

    $('#tnt-add-gallery').on('click', function(e) {
        e.preventDefault();

        if ( mediaFrame ) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title:    'Select Gallery Photos',
            button:   { text: 'Use These Photos' },
            multiple: true,
        });

        mediaFrame.on('select', function() {
            var attachments = mediaFrame.state().get('selection').toJSON();
            var newIds      = attachments.map(function(a) { return a.id; });
            var existing    = $('#tnt_gallery_ids').val();
            var existingIds = existing ? existing.split(',').map(Number).filter(Boolean) : [];

            newIds.forEach(function(id) {
                if ( existingIds.indexOf(id) === -1 ) existingIds.push(id);
            });

            updateGalleryIds( existingIds );
            renderThumbs( existingIds );
        });

        mediaFrame.open();
    });

    /* =========================================================
       RENDER THUMBS
       ========================================================= */
    function renderThumbs( ids ) {
        var $list = $('#tnt-gallery-preview');
        $list.empty();

        ids.forEach(function(id) {
            var $li = $('<li class="tnt-gallery-thumb" draggable="true"></li>')
                .attr('data-id', id)
                .css({ position: 'relative', cursor: 'grab', listStyle: 'none' });

            var $img = $('<img>')
                .attr({ src: '', width: 80, height: 60 })
                .css({ display: 'block', objectFit: 'cover', borderRadius: '3px', border: '2px solid #ddd', pointerEvents: 'none' });

            var $remove = $('<span class="tnt-remove-img" title="Remove">&#10005;</span>')
                .css({ position: 'absolute', top: 0, right: 0, background: '#c00', color: '#fff',
                       fontSize: '10px', padding: '1px 5px', cursor: 'pointer',
                       borderRadius: '0 0 0 3px', lineHeight: '16px' });

            $li.append($img).append($remove);
            $list.append($li);

            // Fetch thumb URL
            wp.ajax.post('query-attachments', { query: { post__in: [id] } }).done(function(res) {
                if ( res && res[0] ) {
                    var url = res[0].sizes && res[0].sizes.thumbnail ? res[0].sizes.thumbnail.url : res[0].url;
                    $img.attr('src', url);
                }
            });

            attachDragEvents($li[0]);
        });
    }

    /* =========================================================
       DRAG AND DROP REORDER
       ========================================================= */
    var dragSrc = null;

    function attachDragEvents(el) {
        el.addEventListener('dragstart', function(e) {
            dragSrc = el;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(function() { el.style.opacity = '0.4'; }, 0);
        });

        el.addEventListener('dragend', function() {
            el.style.opacity = '1';
            document.querySelectorAll('.tnt-gallery-thumb').forEach(function(t) {
                t.classList.remove('tnt-drag-over');
                t.style.outline = '';
            });
            saveOrder();
        });

        el.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        el.addEventListener('dragenter', function() {
            if ( el !== dragSrc ) {
                el.style.outline = '2px dashed #cc2129';
            }
        });

        el.addEventListener('dragleave', function() {
            el.style.outline = '';
        });

        el.addEventListener('drop', function(e) {
            e.preventDefault();
            if ( dragSrc !== el ) {
                var list     = document.getElementById('tnt-gallery-preview');
                var children = Array.from( list.children );
                var srcIdx   = children.indexOf( dragSrc );
                var tgtIdx   = children.indexOf( el );

                if ( srcIdx < tgtIdx ) {
                    list.insertBefore( dragSrc, el.nextSibling );
                } else {
                    list.insertBefore( dragSrc, el );
                }
                el.style.outline = '';
                saveOrder();
            }
        });
    }

    function saveOrder() {
        var ids = [];
        document.querySelectorAll('#tnt-gallery-preview .tnt-gallery-thumb').forEach(function(el) {
            ids.push( parseInt( el.getAttribute('data-id'), 10 ) );
        });
        updateGalleryIds( ids );
    }

    function updateGalleryIds( ids ) {
        $('#tnt_gallery_ids').val( ids.join(',') );
    }

    /* =========================================================
       REMOVE IMAGE
       ========================================================= */
    $(document).on('click', '.tnt-remove-img', function() {
        var $thumb = $(this).closest('.tnt-gallery-thumb');
        $thumb.remove();
        saveOrder();
    });

    /* =========================================================
       INIT — attach drag events to existing thumbs on page load
       ========================================================= */
    document.querySelectorAll('#tnt-gallery-preview .tnt-gallery-thumb').forEach(function(el) {
        attachDragEvents(el);
    });

    /* =========================================================
       ENGINE COUNT TOGGLE
       ========================================================= */
    function updateEngineBlocks( count ) {
        $('.tnt-engine-block').each(function() {
            var eng = parseInt( $(this).data('engine'), 10 );
            $(this).toggle( eng <= count );
        });
    }

    var initialCount = parseInt( $('#tnt_engine_count').val(), 10 ) || 1;
    updateEngineBlocks( initialCount );

    $('#tnt_engine_count').on('change', function() {
        updateEngineBlocks( parseInt( $(this).val(), 10 ) );
    });

})(jQuery);
