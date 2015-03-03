document.observe 'dom:loaded', ->
  if typeof priceWaiterTokenURL == 'undefined'
    return
  tokenInput = document.getElementById('pricewaiter_configuration_sign_up_token')
  button = document.getElementById('nypwidget_signup')
  scope = document.getElementById('store_switcher')

  if typeof button == 'undefined'
    return

  fetchToken = ->
    new (Ajax.Request)(priceWaiterTokenURL,
      method: 'post'
      parameters: 'scope=' + scope.value
      onSuccess: (transport) ->
        tokenInput.value = transport.responseText
        return
      onComplete: ->
        enableButton()
        return
    )
    return

  pwSignup = ->
    window.open 'https://manage.pricewaiter.com/sign-up?token=' + tokenInput.value
    false

  enableButton = ->
    button.className = button.className.replace(/(?:^|\s)disabled(?!\S)/g, '')
    button.enable()
    return

  button.observe 'click', ->
    pwSignup()
    return

  if tokenInput.value == ''
    fetchToken()
  else
    enableButton()
  return
