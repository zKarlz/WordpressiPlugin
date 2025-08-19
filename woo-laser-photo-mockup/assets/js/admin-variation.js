jQuery(function($){
    function initUploader(button){
        var frame;
        button.on('click', function(e){
            e.preventDefault();
            var input = $(this).prev('.llp-media-field');
            if(frame){
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Select image',
                button: {text: 'Use image'},
                multiple: false
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                input.val(attachment.id);
            });
            frame.open();
        });
    }
    $('.llp-select-media').each(function(){
        initUploader($(this));
    });
});
