function decodeQueryString(queryString) {
    var query = {};
    var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
    }
    return query;
}

function X_inlineTableEdit_removeArrayElement(fieldName, $elem) {
    var $arrayfield = $elem.closest('[data-i]').closest('.arrayfield');
    $elem.closest('[data-i]').remove();
    $arrayfield.parent().find('input[name="'+fieldName+'"]').trigger('change');
}

function X_inlineTableEdit_addArrayElement(fieldName, structure, $elem) {
    var $arrayField = $elem.closest('.arrayfield');
    var nextIndex = 0;
    $arrayField.find('[data-i]').each(function(){
        var i = +$(this).attr('data-i');
        if (i >= nextIndex) {
            nextIndex = i+1;
        }
    });
    var $parent = null;
    if ($arrayField[0].tagName=='TABLE') {
        $parent = $('<tr>').insertBefore($elem.closest('tr'));
    } else {
        $parent = $('<div>').insertBefore($elem);
    }
    $parent.attr('data-i', nextIndex);

    var appendField = function(inputName, structure, nbfields, $parent) {
        switch (typeof structure) {
            case 'string':
                var attributes = {
                    label: inputName.replace(/^(.*\[)?(\w+)\]?$/, '$2').replace('_', ' ').trim(),
                };
                if (structure.match(/^\w+\?/)) {
                    $.extend(attributes, decodeQueryString(structure.replace(/^(\w+)\?(.*)$/, '$2')));
                    structure = structure.replace(/^(\w+)\?(.*)$/, '$1');
                }
                var autocompleteValue = 'false_'+inputName.replace(/[\]\[]+/g, '_');
                switch (structure) {
                    default:
                        $('<input>').appendTo($parent)
                            .attr('type', structure || 'text')
                            .attr('name', inputName)
                            .attr('placeholder', attributes.label)
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('data-nbfields', nbfields)
                            .attr('value', '')
                        ;
                    break;
                    case 'textarea':
                        $('<textarea>').appendTo($parent)
                            .attr('name', inputName)
                            .attr('placeholder', attributes.label)
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('data-nbfields', nbfields)
                        ;
                    break;
                }
            break;
            case 'object':
                if (structure === null) {
                    appendField(inputName, '', 1, $parent);
                } else {
                    if ($parent[0].tagName == 'TR') {
                        for (var i in structure) {
                            var $td = $('<td>').appendTo($parent);
                            appendField(inputName+'['+i+']', structure[i], Object.keys(structure).length, $td);
                        }
                    } else {
                        for (var i in structure) {
                            appendField(inputName+'['+i+']', structure[i], Object.keys(structure).length, $parent);
                        }
                    }
                }
            break;
        }
    };
    appendField(fieldName+'['+nextIndex+']', structure, 1, $parent);
    
    $parent.find('input').get(0).focus();

    if ($parent[0].tagName == 'TR') {
        $parent = $('<td>').appendTo($parent);
    }

    $('<i>').attr('class', 'fas fa-times').on('click', function(){
        X_inlineTableEdit_removeArrayElement(fieldName, $(this));
    }).appendTo($parent);
}
