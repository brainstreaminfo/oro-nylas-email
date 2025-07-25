define(['jquery'], function ($) {
    'use strict';

    return {
        init: function () {
            console.log('Global script loaded on all pages');
            $(document).on('click', 'body', function () {
                console.log('Body clicked globally');
            });
        }
    };
});
