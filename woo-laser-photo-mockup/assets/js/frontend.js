jQuery(function($){
    var fileInput   = $('#llp-file');
    var editor      = $('#llp-editor');
    var preview     = $('#llp-preview');
    var previewImg  = $('#llp-preview img');
    var finalizeBtn = $('#llp-finalize');
    var assetField  = $('#llp-asset-id');
    var thumbField  = $('#llp-thumb-url');
    var transformField = $('#llp-transform');
    var currentVariation = $('input.variation_id').val() || 0;
    var addToCartBtn = $('.single_add_to_cart_button').prop('disabled', true);

    var cropperImg = $('#llp-canvas');
    var cropper = null;

    function getBounds(){
        if(window.llpBounds && window.llpBounds[currentVariation]){
            return window.llpBounds[currentVariation];
        }
        return { x:0, y:0, width:200, height:200, rotation:0 };
    }

    function initCropper(url){
        var bounds = getBounds();
        editor.show().css({width:bounds.width, height:bounds.height});
        preview.show();
        cropperImg.attr('src', url);
        if(cropper){ cropper.destroy(); }
        cropper = new Cropper(cropperImg[0], {
            viewMode:1,
            dragMode:'move',
            aspectRatio: bounds.width / bounds.height,
            autoCropArea:1,
            movable:true,
            zoomable:true,
            rotatable:true,
            scalable:true,
            cropBoxMovable:false,
            cropBoxResizable:false,
            ready: function(){
                cropper.setCropBoxData({ width: bounds.width, height: bounds.height });
                updateTransform();
            },
            crop: function(){
                updateTransform();
            }
        });
    }

    function getTransform(){
        if(!cropper){
            return { crop:{x:0,y:0,width:0,height:0}, scale:1, rotation:0 };
        }
        var data = cropper.getData();
        return {
            crop: { x: data.x, y: data.y, width: data.width, height: data.height },
            scale: data.scaleX || 1,
            rotation: data.rotate || 0
        };
    }

    function updateTransform(){
        if(!cropper) return;
        var transform = getTransform();
        transformField.val(JSON.stringify(transform));
        var bounds = getBounds();
        var canvas = cropper.getCroppedCanvas({ width: bounds.width, height: bounds.height });
        if(canvas){
            previewImg.attr('src', canvas.toDataURL());
        }
    }

    $('form.variations_form').on('found_variation', function(e, variation){
        currentVariation = variation.variation_id || 0;
    }).on('reset_data', function(){
        currentVariation = 0;
    });

    fileInput.on('change', function(){
        var file = this.files[0];
        if(!file) return;
        addToCartBtn.prop('disabled', true);
        var reader = new FileReader();
        reader.onload = function(e){
            initCropper(e.target.result);
        };
        reader.readAsDataURL(file);

        var fd = new FormData();
        fd.append('file', file);
        fetch(llpVars.restUrl + '/upload', {
            method: 'POST',
            headers: {'X-WP-Nonce': llpVars.nonce},
            body: fd
        }).then(function(resp){
            if(!resp.ok){
                throw new Error('Upload failed');
            }
            return resp.json();
        }).then(function(res){
            if(res.asset_id){
                assetField.val(res.asset_id);
            }
        }).catch(function(){
            $(document.body).trigger('wc_add_notice', ['Error uploading image. Please try again.', 'error']);
            finalizeBtn.prop('disabled', false);
            addToCartBtn.prop('disabled', false);
        });
    });

    finalizeBtn.on('click', function(){
        if(!assetField.val() || !cropper) return;
        finalizeBtn.prop('disabled', true);
        addToCartBtn.prop('disabled', true);
        currentVariation = $('input.variation_id').val() || currentVariation;
        var transform = getTransform();
        transformField.val(JSON.stringify(transform));

        fetch(llpVars.restUrl + '/finalize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': llpVars.nonce
            },
            body: JSON.stringify({
                asset_id: assetField.val(),
                variation_id: currentVariation,
                transform: transform
            })
        }).then(function(r){
            if(!r.ok){
                throw new Error('Finalize failed');
            }
            return r.json();
        }).then(function(res2){
            if(res2.thumb){
                previewImg.attr('src', res2.thumb);
                thumbField.val(res2.thumb);
                preview.show();
                editor.hide();
                addToCartBtn.prop('disabled', false);
            }
        }).catch(function(){
            $(document.body).trigger('wc_add_notice', ['Error finalizing image. Please try again.', 'error']);
            finalizeBtn.prop('disabled', false);
            addToCartBtn.prop('disabled', false);
        }).finally(function(){
            finalizeBtn.prop('disabled', false);
        });
    });
});

