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
                if (typeof value === 'object' && Object.keys(value).length == 0) value = "";
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

function X_inlineTableEdit_removeArrayElement(fieldName, $elem) {
    var $td = $elem.closest('td');
    $elem.closest('div').remove();
    if ($td.length) {
        $td.find('input[name="'+fieldName+'"]').trigger('change.autosave');
    }
}

function X_inlineTableEdit_addArrayElement(fieldName, structure, $elem) {
    var $parent = $elem.parent();
    var nextIndex = 0;
    $parent.find('div[data-i]').each(function(){
        var i = +$(this).attr('data-i');
        if (i >= nextIndex) {
            nextIndex = i+1;
        }
    });
    var $div = $('<div>').attr('data-i', nextIndex).insertBefore($elem);

    var appendField = function(name, structure, nbfields) {
        nbfields = nbfields || 1;
        switch (typeof structure) {
            case 'string':
                switch (structure) {
                    default:
                        $('<input>').appendTo($div)
                            .attr('type', structure || 'text')
                            .attr('name', name)
                            .attr('data-nbfields', nbfields)
                            .attr('autocomplete', 'false_'+name.replace(/\]\[/g, '_'))
                            .attr('value', '')
                        ;
                    break;
                    case 'textarea':
                        $('<textarea>').appendTo($div)
                            .attr('name', name)
                            .attr('data-nbfields', nbfields)
                            .attr('autocomplete', 'false_'+name.replace(/\]\[/g, '_'))
                        ;
                    break;
                }
            break;
            case 'object':
                if (structure === null) {
                    appendField(name, '');
                } else {
                    for (var i in structure) {
                        appendField(name+'['+i+']', structure[i], Object.keys(structure).length);
                    }
                }
            break;
        }
    };
    appendField(fieldName+'['+nextIndex+']', structure);

    $div.find('input').get(0).focus();
    $('<i>').attr('class', 'fas fa-times').on('click', function(){
        X_inlineTableEdit_removeArrayElement(fieldName, $(this));
    }).appendTo($div);
}
