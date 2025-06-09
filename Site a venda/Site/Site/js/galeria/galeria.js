$(document).ready(function () {
    var $galleryContainer = $('.gallery');

    $galleryContainer.imagesLoaded(function () {
        $galleryContainer.isotope({
            itemSelector: '.item',
            layoutMode: 'fitRows'
        });
    });

    $('.button-group .button').on('click', function () {
        $('.button-group .button').removeClass('active');
        $(this).addClass('active');

        var value = $(this).attr('data-filter');
        $galleryContainer.isotope({
            filter: value === '*' ? '*' : '.' + value
        });
    });
});
