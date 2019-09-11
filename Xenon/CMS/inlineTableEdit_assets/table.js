// Ajax Auto Save
/*
    Custom JS functions : 
        X_tableEditBeforeAjax_FIELDNAME(data, $td) // we may modify data, return false to cancel the ajax request
        X_tableEditAjaxSuccess_FIELDNAME(response, $td) // return true for success, otherwise its considered a failure, string is an error message
*/
$(document).on('change.autosave', '.inlineTableEdit input[name], .inlineTableEdit select[name], .inlineTableEdit textarea[name], .inlineTableEdit .wysiwyg', function(){
    var $input = $(this);
    var autoSaveAction = function(){
        if ($input.get(0).tagName == "INPUT" && $input.get(0).type == "file") return;
        var $parent = $input.closest('[data-fieldname]');
        var customBeforeFunc = 'X_tableEditBeforeAjax_'+$parent.attr('data-fieldname');
        var customSuccessFunc = 'X_tableEditAjaxSuccess_'+$parent.attr('data-fieldname');
        var data = {
            id: $parent.attr('data-id'),
        };
        $parent.find('[name]').each(function(){
            var value = $(this).hasClass('wysiwyg')? $(this).html() : $(this).val();
            if ($(this).attr('name').match(/\[\]$/)) {
                var name = $(this).attr('name').replace(/\[\]$/, '');
                if (!$(this).prop('disabled')) {
                    if (this.tagName != "INPUT" || this.type != "checkbox" || $(this).prop('checked')) {
                        if (typeof data[name] != 'object') {
                            data[name] = [];
                        }
                        data[name].push(value);
                    }
                }
            } else {
                if (this.tagName == "INPUT" && this.type == "checkbox") {
                    value = $(this).prop('checked')? 1:0;
                }
                if (typeof value === 'object' && value !== null && Object.keys(value).length == 0) value = "";
                if (typeof value === 'undefined') value = "";
                if (!$(this).prop('disabled')) data[$(this).attr('name')] = value;
            }
        });
        if (typeof window[customBeforeFunc] === 'function') {
            if (window[customBeforeFunc](data, $parent) === false) {
                return;
            }
        }
        $parent.attr('status', "saving");
        $.ajax({
            url: '',
            method: 'post',
            data: data,
            success: function(response){
                if (typeof window[customSuccessFunc] === 'function') {
                    response = window[customSuccessFunc](response, $parent);
                } else {
                    if (response === "OK") {
                        response = true;
                    }
                }
                if (response === true) {
                    $parent.attr('status', "success");
                    setTimeout(function(){$parent.attr('status', "");}, 1000);
                } else {
                    $parent.attr('status', "error");
                    alert(response);
                }
            },
            error: function(response){
                $parent.attr('status', "error");
                alert(response);
            },
            complete: function(){

            }
        });
    };
    if ($input.is('[delayed]')) {
        setTimeout(autoSaveAction, +$input.attr('delayed'));
    } else {
        autoSaveAction();
    }
});

// Ajax Delete
$(document).on('click', '.inlineTableEdit [data-delete-id]', function(){
    if (confirm("Delete this entry ?")) {
        $.ajax({
            url: '',
            method: 'post',
            data: {
                'id': $(this).attr('data-delete-id'),
                '_ACTION_': 'DELETE',
            },
            success: function(response) {
                location.reload(true);
            },
            error: function(response) {
                alert(response);
            }
        });
    }
});
