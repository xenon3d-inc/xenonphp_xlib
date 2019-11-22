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
    $arrayfield.parent().find('input[name="'+fieldName+'"],textarea[name="'+fieldName+'"],select[name="'+fieldName+'"]').trigger('change');
}

function X_inlineTableEdit_addArrayElement(fieldName, structure, $elem, options) {
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

    var appendField = function(inputName, structure, nbfields, $parent, options) {
        options = options || {};
        switch (typeof structure) {
            case 'string':
                var fieldName = inputName.replace(/^(.*\[)?([\w#\$-]+)\]?$/, '$2');
                var attributes = {
                    label: fieldName.replace('_', ' ').trim(),
                };
                if (structure.match(/^\w+\?/)) {
                    $.extend(attributes, decodeQueryString(structure.replace(/^(\w+)\?(.*)$/, '$2')));
                    structure = structure.replace(/^(\w+)\?(.*)$/, '$1');
                }
                if (!attributes['placeholder'] && structure === 'timer') attributes['placeholder'] = '00:00';
                if (!attributes['placeholder']) attributes['placeholder'] = attributes['label'];
                var autocompleteValue = 'false_'+inputName.replace(/[\]\[]+/g, '_');
                if (attributes['readonly'] && structure == 'info') {
                    $('<span>').appendTo($parent).text(attributes.placeholder).attr('title', attributes.label);
                } else switch (structure) {
                    case 'checkbox':
                        $('<input>').appendTo($parent)
                            .attr('type', 'hidden')
                            .attr('name', inputName)
                            .attr('value', '0')
                        ; // no break
                    default:
                        $('<input>').appendTo($parent)
                            .attr('type', structure || 'text')
                            .attr('name', inputName)
                            .attr('data-field', fieldName)
                            .attr('placeholder', attributes.placeholder)
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('readonly', !!attributes['readonly'])
                            .attr('data-nbfields', nbfields)
                            .attr('value', structure=='checkbox'?'1':'')
                        ;
                    break;
                    case 'decimal':
                        $('<input>').appendTo($parent)
                            .attr('type', 'number')
                            .attr('step', '0.01')
                            .attr('min', '0.0')
                            .attr('name', inputName)
                            .attr('data-field', fieldName)
                            .attr('placeholder', attributes.placeholder)
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('readonly', !!attributes['readonly'])
                            .attr('data-nbfields', nbfields)
                            .attr('value', '')
                        ;
                    break;
                    case 'timer':
                        $('<input>').appendTo($parent)
                            .attr('type', 'timer')
                            .attr('name', inputName)
                            .attr('data-field', fieldName)
                            .attr('placeholder', '00:00')
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('readonly', !!attributes['readonly'])
                            .attr('data-nbfields', nbfields)
                            .attr('value', '')
                        ;
                    break;
                    case 'textarea':
                        $('<textarea>').appendTo($parent)
                            .attr('name', inputName)
                            .attr('data-field', fieldName)
                            .attr('placeholder', attributes.placeholder)
                            .attr('title', attributes.label)
                            .attr('autocomplete', autocompleteValue)
                            .attr('readonly', !!attributes['readonly'])
                            .attr('data-nbfields', nbfields)
                        ;
                    break;
                    case 'wysiwyg':
                        $('<div>').appendTo($parent)
                            .addClass('wysiwyg')
                            .attr('data-field', fieldName)
                            .on('click', function(){
                                wysiwyg_CKEditor_inline_edit(this, event);
                            })
                            .on('blur', function(){
                                $(this).next().val($(this).html()).trigger('change');
                            })
                            .after(
                                $('<textarea style="display:none;">')
                                    .attr('data-field', fieldName)
                                    .attr('name', inputName)
                            )
                        ;
                    break;
                    case 'select':
                        var $select = $('<select>').appendTo($parent)
                        .attr('name', inputName)
                        .attr('data-field', fieldName)
                        .attr('title', attributes.label)
                        .attr('autocomplete', autocompleteValue)
                        .attr('readonly', !!attributes['readonly'])
                        .attr('data-nbfields', nbfields)
                        ;
                        if (attributes.autocomplete_ajax) {
                            $select.attr('autocomplete_ajax', attributes.autocomplete_ajax);
                        } else if (typeof attributes.autocomplete_ajax !== 'undefined') {
                            $select.attr('autocomplete_ajax', options);
                        }
                        attributes.options = attributes.options? attributes.options.split(',') : [];
                        for (var i in attributes.options) {
                            $('<option>').appendTo($select)
                                .attr('value', attributes.options[i])
                                .text(attributes.options[i])
                            ;
                        }
                        if (typeof options === 'object') for (var i in options) {
                            $('<option>').appendTo($select)
                                .attr('value', i)
                                .text(options[i])
                            ;
                        }
                    break;
                }
            break;
            case 'object':
                if (structure === null) {
                    appendField(inputName, '', 1, $parent, options);
                } else {
                    if ($parent[0].tagName == 'TR') {
                        for (var k in structure) {
                            var $td = $('<td>').appendTo($parent);
                            appendField(inputName+'['+k+']', structure[k], Object.keys(structure).length, $td, options[k]);
                        }
                    } else {
                        for (var k in structure) {
                            appendField(inputName+'['+k+']', structure[k], Object.keys(structure).length, $parent, options[k]);
                        }
                    }
                }
            break;
        }
    };
    appendField(fieldName+'['+nextIndex+']', structure, 1, $parent, options);
    
    $parent.find('input,select,textarea').get(0).focus();

    if ($parent[0].tagName == 'TR') {
        $parent = $('<td>').appendTo($parent);
    }

    $('<i>').appendTo($parent).attr('class', 'fas fa-times').on('click', function(){
        X_inlineTableEdit_removeArrayElement(fieldName, $(this));
    });
}
