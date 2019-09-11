$(document).on('submit', 'form.inlineEditTable_add', function(e){
    e.preventDefault();
    $.ajax({
        url: '',
        method: 'post',
        data: $(this).serialize(),
        success: function(response){
            if (response === "OK") {
                location.reload(true);
            } else {
                alert(response);
            }
        },
        error: function(response){
            alert(response);
        }
    });
});
$(document).on('click', 'form.inlineEditTable_add label', function(e){
    e.preventDefault();
});
