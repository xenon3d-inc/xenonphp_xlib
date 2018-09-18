function showLoading(fadeTime = 300, callback = undefined) {
    if (fadeTime > 0) {
        $('body > #loading').fadeIn(fadeTime, callback);
    } else {
        $('body > #loading').show();
        if (callback) callback();
    }
}
function hideLoading(fadeTime = 300, callback = undefined) {
    if (fadeTime > 0) {
        $('body > #loading').fadeOut(fadeTime, callback);
    } else {
        $('body > #loading').hide();
        if (callback) callback();
    }
}
var delayedLoadingIconTimeout = null;
function showLoadingDelayed(delay = 300, fadeTime = 300, callback = undefined) {
    delayedLoadingIconTimeout = setTimeout(function(){
        delayedLoadingIconTimeout = null;
        showLoading(fadeTime, callback);
    }, delay);
}
function hideLoadingDelayed(fadeTime = 300, callback = undefined) {
    if (delayedLoadingIconTimeout) {
        clearTimeout(delayedLoadingIconTimeout);
        hideLoading(0, callback);
    } else {
        hideLoading(fadeTime, callback);
    }
}
