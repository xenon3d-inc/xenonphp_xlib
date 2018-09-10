<?php
function X_asset($path) {
    return BASE_URL.$path.'?'.@filemtime(DOCUMENT_ROOT.BASE_URL.$path);
}
