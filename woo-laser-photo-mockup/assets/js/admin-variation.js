jQuery(function($){
    function openUploader(button){
        var input      = button.prev('.llp-media-field');
        var frame = wp.media({
            title: button.data('title') || 'Select image',
            button: {text: button.data('button') || 'Use image'},
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.id).trigger('change');
            var img = $('<img>', { src: attachment.url, alt: attachment.alt || attachment.title || '' });
            button.siblings('.llp-image-preview').html(img);
            button.siblings('.llp-remove-media').show();
            if(input.attr('name').indexOf('llp_base_image_id') !== -1){
                var container = button.closest('.llp-variation-fields');
                container.find('.llp-base-image').remove();
                container.find('.llp-bounds-wrapper').prepend('<img src="'+attachment.url+'" class="llp-base-image" />');
                setupBounds(container);
            }
        });
        frame.open();
    }

    $(document).on('click', '.llp-select-media', function(e){
        e.preventDefault();
        openUploader($(this));
    });

    $(document).on('click', '.llp-remove-media', function(e){
        e.preventDefault();
        var button = $(this);
        var input  = button.siblings('.llp-media-field');
        input.val('').trigger('change');
        button.siblings('.llp-image-preview').empty();
        button.hide();
        if(input.attr('name').indexOf('llp_base_image_id') !== -1){
            var container = button.closest('.llp-variation-fields');
            container.find('.llp-base-image').remove();
            container.find('.llp-overlay').removeAttr('style');
            container.find('.llp-bounds-input').val('');
            container.find('.llp-rotation').val('0').trigger('change');
        }
    });

    function setupBounds(container){
        var wrapper = container.find('.llp-bounds-wrapper');
        var overlay = wrapper.find('.llp-overlay');
        var boundsInput = container.find('.llp-bounds-input');
        var rotation = container.find('.llp-rotation');

        if(!wrapper.find('.llp-base-image').length){
            return;
        }

        wrapper.css('position','relative');
        overlay.css('position','absolute');

        if(overlay.data('ui-draggable')) overlay.draggable('destroy');
        if(overlay.data('ui-resizable')) overlay.resizable('destroy');

        overlay.draggable({ containment: 'parent', stop: updateBounds });
        overlay.resizable({ containment: 'parent', stop: updateBounds });
        rotation.off('input.llp change.llp').on('input.llp change.llp', updateBounds);

        function updateBounds(){
            var pos = overlay.position();
            var rot = parseFloat(rotation.val()) || 0;
            overlay.css('transform','rotate('+rot+'deg)');
            var bounds = {
                x: Math.round(pos.left),
                y: Math.round(pos.top),
                width: Math.round(overlay.width()),
                height: Math.round(overlay.height()),
                rotation: rot
            };
            boundsInput.val(JSON.stringify(bounds));
        }

        var existing = boundsInput.val();
        if(existing){
            try{
                var data = JSON.parse(existing);
                overlay.css({ left: data.x, top: data.y, width: data.width, height: data.height });
                rotation.val(data.rotation || 0);
                overlay.css('transform','rotate('+(data.rotation || 0)+'deg)');
            }catch(err){
                overlay.css({ left:0, top:0, width:100, height:100 });
                rotation.val(0);
            }
        }else{
            overlay.css({ left:0, top:0, width:100, height:100 });
            rotation.val(0);
        }
        updateBounds();
    }

    function initBounds(){
        $('.llp-variation-fields').each(function(){
            setupBounds($(this));
        });
    }

    $(document).on('woocommerce_variations_loaded woocommerce_variation_added', initBounds);
    initBounds();
});
