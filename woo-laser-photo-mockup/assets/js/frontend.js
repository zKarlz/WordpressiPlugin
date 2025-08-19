jQuery(function($){
    var fileInput = $('#llp-file');
    var preview   = $('#llp-preview');
    var img       = $('#llp-preview img');
    var assetField = $('#llp-asset-id');
    var thumbField = $('#llp-thumb-url');
    var currentVariation = $('input.variation_id').val() || 0;

    function getTransform(){
        var crop = {
            x: parseFloat(img.data('crop-x')) || 0,
            y: parseFloat(img.data('crop-y')) || 0,
            width: parseFloat(img.data('crop-width')) || img.width(),
            height: parseFloat(img.data('crop-height')) || img.height()
        };
        var scale = parseFloat(img.data('scale')) || 1;
        var rotation = parseFloat(img.data('rotation')) || 0;
        return { crop: crop, scale: scale, rotation: rotation };
    }

    // Track variation selection
    $('form.variations_form').on('found_variation', function(e, variation){
        currentVariation = variation.variation_id || 0;
    }).on('reset_data', function(){
        currentVariation = 0;
    });

    fileInput.on('change', function(){
        var file = this.files[0];
        if(!file) return;
        var fd = new FormData();
        fd.append('file', file);
        fetch(llpVars.restUrl + '/upload', {
            method: 'POST',
            headers: {'X-WP-Nonce': llpVars.nonce},
            body: fd
        }).then(resp => resp.json()).then(function(res){
            if(res.asset_id){
                assetField.val(res.asset_id);
                var transform = getTransform();
                fetch(llpVars.restUrl + '/finalize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': llpVars.nonce
                    },
                    body: JSON.stringify({asset_id: res.asset_id, variation_id: currentVariation, transform: transform})
                }).then(r => r.json()).then(function(res2){
                    if(res2.thumb){
                        img.attr('src', res2.thumb);
                        preview.show();
                        thumbField.val(res2.thumb);
                    }
                });
            }
        });
    });
});
