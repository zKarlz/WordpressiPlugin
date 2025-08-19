jQuery(function($){
    var fileInput   = $('#llp-file');
    var editor      = $('#llp-editor');
    var preview     = $('#llp-preview');
    var img         = $('#llp-preview img');
    var finalizeBtn = $('#llp-finalize');
    var assetField  = $('#llp-asset-id');
    var thumbField  = $('#llp-thumb-url');
    var transformField = $('#llp-transform');
    var currentVariation = $('input.variation_id').val() || 0;

    var canvas = new fabric.Canvas('llp-canvas', { selection: false });
    var fabricImg = null;

    function getBounds(){
        if(window.llpBounds && window.llpBounds[currentVariation]){
            return window.llpBounds[currentVariation];
        }
        return { x:0, y:0, width:200, height:200, rotation:0 };
    }

    function initCanvas(url){
        var bounds = getBounds();
        canvas.clear();
        canvas.setWidth(bounds.width);
        canvas.setHeight(bounds.height);
        fabric.Image.fromURL(url, function(oImg){
            fabricImg = oImg;
            oImg.set({ left:0, top:0, originX:'left', originY:'top' });
            canvas.add(oImg);
            canvas.renderAll();
            updateTransform();
        });
        editor.show();
        preview.show();
    }

    function clamp(){
        if(!fabricImg) return;
        var maxLeft = 0;
        var maxTop = 0;
        var minLeft = canvas.width - fabricImg.getScaledWidth();
        var minTop = canvas.height - fabricImg.getScaledHeight();
        if(fabricImg.left > maxLeft) fabricImg.left = maxLeft;
        if(fabricImg.top > maxTop) fabricImg.top = maxTop;
        if(fabricImg.left < minLeft) fabricImg.left = minLeft;
        if(fabricImg.top < minTop) fabricImg.top = minTop;
    }
    canvas.on('object:moving', function(){ clamp(); updateTransform(); });
    canvas.on('object:scaling', function(){ clamp(); updateTransform(); });
    canvas.on('object:rotating', function(){ clamp(); updateTransform(); });

    function getTransform(){
        if(!fabricImg){
            return { crop:{x:0,y:0,width:0,height:0}, scale:1, rotation:0 };
        }
        var scale = fabricImg.scaleX;
        var rotation = fabricImg.angle;
        var crop = {
            x: -fabricImg.left / scale,
            y: -fabricImg.top / scale,
            width: canvas.width / scale,
            height: canvas.height / scale
        };
        return { crop: crop, scale: scale, rotation: rotation };
    }

    function updateTransform(){
        var transform = getTransform();
        transformField.val(JSON.stringify(transform));
        if(fabricImg){
            img.attr('src', canvas.toDataURL());
        }
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
        var reader = new FileReader();
        reader.onload = function(e){
            initCanvas(e.target.result);
        };
        reader.readAsDataURL(file);
        var fd = new FormData();
        fd.append('file', file);
        fetch(llpVars.restUrl + '/upload', {
            method: 'POST',
            headers: {'X-WP-Nonce': llpVars.nonce},
            body: fd
        }).then(resp => resp.json()).then(function(res){
            if(res.asset_id){
                assetField.val(res.asset_id);
            }
        });
    });

    finalizeBtn.on('click', function(){
        if(!assetField.val()) return;
        // Ensure we have the latest selected variation
        currentVariation = $('input.variation_id').val() || currentVariation;
        updateTransform();
        var transform = JSON.parse(transformField.val() || '{}');
        fetch(llpVars.restUrl + '/finalize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': llpVars.nonce
            },
            body: JSON.stringify({asset_id: assetField.val(), variation_id: currentVariation, transform: transform})
        }).then(r => r.json()).then(function(res2){
            if(res2.thumb){
                img.attr('src', res2.thumb);
                thumbField.val(res2.thumb);
                preview.show();
                editor.hide();
            }
        });
    });
});
