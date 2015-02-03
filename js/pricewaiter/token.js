document.observe("dom:loaded", function() {
    if (typeof priceWaiterTokenURL == 'undefined') {
        return;
    }
    var tokenInput = document.getElementById('pricewaiter_configuration_sign_up_token');
    var button = document.getElementById('nypwidget_signup');
    var scope = document.getElementById('store_switcher');

    var fetchToken = function() {
        new Ajax.Request(priceWaiterTokenURL, {
            method: 'post',
            parameters: 'scope=' + scope.value,
            onSuccess: function(transport) {
                tokenInput.value = transport.responseText;
            },
            onComplete: function() {
                enableButton();
            }
        });
    };

    var pwSignup = function() {
        window.open('https://manage.pricewaiter.com/sign-up?token=' + tokenInput.value);
        return false;
    };

    var enableButton = function() {
        button.className = button.className.replace(/(?:^|\s)disabled(?!\S)/g , '');
        button.enable();
    };

    button.observe('click', function() {
        pwSignup()
    });

    if (tokenInput.value == '') {
        fetchToken();
    } else {
        enableButton();
    }
});
