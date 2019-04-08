// ajaxSubmit <?php
/*
 * Ajax Form Submit, by Olivier St-Laurent
 * Version 1.0
 *
 *
 * Usage :
 *
 *      <form action="" method="post" onsubmit="ajaxSubmit(this, event)">
 *
 *
 *///?>

function ajaxSubmit(elem, event) {
    switch (elem.nodeName) {
        case 'INPUT':
        case 'TEXTAREA':
        case 'SELECT':
            $(elem.form).submit();
            break;
        case 'FORM':
            var $form = $(elem);
            $.ajax({
                url: $form.attr('action'),
                type: $form.attr('method'),
                data: $form.serialize(),
                success: function(response, textStatus, jqXHR){
                    var redirectUrl = jqXHR.getResponseHeader('X-Redirect');
                    var newUrl = jqXHR.getResponseHeader('X-ReplaceUrl');
                    if (newUrl !== null) {
                        window.history.replaceState({}, "", newUrl);
                    }
                    if (redirectUrl !== null) {
                        window.history.replaceState({}, "", redirectUrl);
                        window.location.reload(true);
                    }
                }
            });
            if (event) {
                event.preventDefault();
            }
            break;
    }
}
