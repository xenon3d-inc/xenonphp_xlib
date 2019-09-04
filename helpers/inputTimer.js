function X_fixInputTimer($elem) {
    if ($elem.val().match(/^\d\d:$/)) {
        $elem.val($elem.val()+'00');
    } else if ($elem.val().match(/^\d\d:\d$/)) {
        $elem.val($elem.val()+'0');
    } else if ($elem.val().match(/^\d$/)) {
        $elem.val('0'+$elem.val()+':00');
    }
}
$(document).on('mousedown mouseup', 'input[type="timer"]', function(e){
    $(this).focus().select();
    e.preventDefault();
});
$(document).on('keydown', 'input[type="timer"]', function(e){
    var k = e.keyCode;
    console.log(k);
    if ((k >= 48 && k <= 57) || (k >= 96 && k <= 105)) {
        var n = (k>57? k-96 : k-48);
        // Number input
        if (this.selectionEnd - this.selectionStart) {
            $(this).val(n);
        } else {
            if ($(this).val().length < 5) {
                $(this).val($(this).val() + n);
            }
            if ($(this).val().length == 2) {
                $(this).val($(this).val() + ':');
            }
        }
        e.preventDefault();
    } else if (k == 8) {
        // Erase
        $(this).val($(this).val().substr(0, -1));
        e.preventDefault();
    } else if (k == 46) {
        // Delete
        $(this).val('');
        e.preventDefault();
    } else if (k == 9) {
        // Tab
        X_fixInputTimer($(this));
    } else if (k == 13) {
        // Enter
        X_fixInputTimer($(this));
    } else if (k == 116) {
        // F5
    } else {
        e.preventDefault();
        return false;
    }
});
$(document).on('blur', 'input[type="timer"]', function(e){
    X_fixInputTimer($(this));
});
