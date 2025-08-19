jQuery(function($){
    var fileInput = $('#llp-file');
    var preview   = $('#llp-preview');
    var img       = $('#llp-preview img');
    var assetField = $('#llp-asset-id');
    var thumbField = $('#llp-thumb-url');

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
                // Finalize immediately with dummy transform; real app would send user transform data
                var variation = $('input.variation_id').val() || 0;
                fetch(llpVars.restUrl + '/finalize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': llpVars.nonce
                    },
                    body: JSON.stringify({asset_id: res.asset_id, variation_id: variation, transform: {}})
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
