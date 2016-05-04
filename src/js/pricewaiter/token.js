(function() {
  document.observe('dom:loaded', function() {
    var apiSecret, button, enableButton, fetchSecret, fetchToken, pwSignup, scope, tokenInput;
    if (typeof priceWaiterTokenURL === 'undefined') {
      return;
    }
    tokenInput = document.getElementById('pricewaiter_configuration_sign_up_token');
    button = document.getElementById('nypwidget_signup');
    scope = document.getElementById('store_switcher');
    apiSecret = document.getElementById('pricewaiter_configuration_api_secret');
    if (typeof button === 'undefined' || button === null) {
      return;
    }
    fetchToken = function() {
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
    fetchSecret = function() {
      new Ajax.Request(priceWaiterSecretURL, {
        method: 'post',
        onSuccess: function(transport) {
          apiSecret.value = transport.responseJSON.secret;
        }
      });
    };
    pwSignup = function() {
      window.open('https://manage.pricewaiter.com/sign-up?token=' + tokenInput.value);
      return false;
    };
    enableButton = function() {
      button.className = button.className.replace(/(?:^|\s)disabled(?!\S)/g, '');
      button.enable();
    };
    button.observe('click', function() {
      pwSignup();
    });
    if (tokenInput.value === '') {
      fetchToken();
    } else {
      enableButton();
    }
    if (apiSecret.value === '') {
      return fetchSecret();
    }
  });

}).call(this);
