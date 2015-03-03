###
# Copyright 2013-2014 Price Waiter, LLC
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      http://www.apache.org/licenses/LICENSE-2.0
#
#  Unless required by applicable law or agreed to in writing, software
#  distributed under the License is distributed on an "AS IS" BASIS,
#  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#  See the License for the specific language governing permissions and
#  limitations under the License.
#
###

$(document).observe 'dom:loaded', ->
  if typeof PriceWaiterOptions == 'object'

    PriceWaiterOptions.onButtonClick = (PriceWaiter, platformOnButtonClick) ->
      productForm = $('product_addtocart_form')
      productConfiguration = productForm.serialize()
      PriceWaiter.setMetadata '_magento_product_configuration', encodeURIComponent(productConfiguration)
      true

    PriceWaiterOptions.onload = (PriceWaiter) ->

      simplesSelect = (select, name) ->
        select.observe 'change', ->
          PriceWaiter.setProductOption name, select.options[select.selectedIndex].text
          return
        return

      simplesInput = (select, name) ->
        if select.type == 'text' or select.tagName == 'TEXTAREA'
          select.observe 'change', ->
            PriceWaiter.setProductOption name, select.value
            return
        else
          select.observe 'change', ->
            optionValue = select.next('span').select('label')[0].innerHTML
            optionValue = optionValue.replace(/\s*<span.*\/span>/, '')
            PriceWaiter.setProductOption name, optionValue
            return
        return

      handleSimples = ->
        # if there are no custom options, we don't have anything to do
        if typeof opConfig == 'undefined'
          return
        # If this product has an upload file option, we can't use the NYP widget
        productForm = $('product_addtocart_form')
        if productForm.getInputs('file').length != 0
          console.log 'The PriceWaiter Name Your Price Widget does not support upload file options.'
          $$('div.name-your-price-widget').each (pww) ->
            pww.setStyle display: 'none'
            return
        # Grab the updated price before opening the PriceWaiter window
        PriceWaiter.originalOpen = PriceWaiter.open

        PriceWaiter.open = ->
          productPrice = 0
          priceElement = document.getElementsByRegex('^product-price-')
          innerSpan = priceElement[0].select('span')
          if typeof innerSpan[0] == 'undefined'
            productPrice = priceElement[0].innerHTML
          else
            productPrice = innerSpan[0].innerHTML
          PriceWaiter.setPrice productPrice
          PriceWaiter.originalOpen()
          return

        # Find the available options, and bind to them
        productCustomOptions = $$('.product-custom-option')
        for current of productCustomOptions
          if !isNaN(parseInt(current, 10))
            # Find the option label
            optionLabel = productCustomOptions[current].up('dd').previous('dt').select('label')[0]
            optionName = optionLabel.innerHTML.replace(/^<em.*\/em>/, '')
            # Check if this is a required option
            if optionLabel.hasClassName('required')
              PriceWaiter.setProductOptionRequired optionName
            # we have to handle different inputs a bit differently.
            switch productCustomOptions[current].tagName
              when 'SELECT'
                simplesSelect productCustomOptions[current], optionName
              when 'INPUT', 'TEXTAREA'
                simplesInput productCustomOptions[current], optionName
        return

      handleConfigurables = ->
        # Bind to each configurable options 'change' event
        spConfig.settings.each (setting) ->
          attributeId = $(setting).id
          attributeId = attributeId.replace(/attribute/, '')
          optionName = spConfig.config.attributes[attributeId].label
          # If this option is required, tell the PriceWaiter widget about the requirement
          if $(setting).hasClassName('required-entry') and typeof PriceWaiter.setProductOptionRequired == 'function'
            PriceWaiter.setProductOptionRequired optionName, true
          Event.observe setting, 'change', (event) ->
            # Update PriceWaiter's price and options when changes are made
            PriceWaiter.setPrice Number(spConfig.config.basePrice) + Number(spConfig.reloadPrice())
            optionValue = if setting.value != '' then setting.options[setting.selectedIndex].innerHTML else undefined
            # if the option value is undefined, clear the option. Otherwise, set the newly selected option.
            if optionValue == undefined
              PriceWaiter.clearProductOption optionName
            else
              PriceWaiter.setProductOption optionName, optionValue
            return
          return
        return

      handleBundles = ->
        # Find options that are marked as required
        requiredOptions = []
        bundleElements = document.getElementsByRegex('^bundle-option-')
        rePattern = /\[(\d*)\]/
        for bundleOption of bundleElements
          if !isNaN(parseInt(bundleOption, 10))
            obj = bundleElements[bundleOption]
            if obj.hasClassName('required-entry') or obj.hasClassName('validate-one-required-by-name')
              matched = rePattern.exec(obj.name)
              requiredOptions.push parseInt(matched[1], 10)
        requiredOptions = requiredOptions.uniq()
        # Add required Options to PriceWaiter
        for key of bundle.config.options
          if requiredOptions.indexOf(parseInt(key, 10)) > -1
            opt = bundle.config.options[key]
            PriceWaiter.setProductOptionRequired opt.title, true
        # Bind to event fired when price is changed on bundle
        document.observe 'bundle:reload-price', (event) ->
          PriceWaiter.setPrice event.memo.priceInclTax
          bSelected = event.memo.bundle.config.selected
          bOptions = event.memo.bundle.config.options
          for current of bSelected
            if isNaN(current)
              continue
            # Find which value is selected
            currentSelected = bSelected[current]
            if currentSelected.length == 0
              # If none, unset the Product option
              PriceWaiter.clearProductOption bOptions[current].title
            else
              # Otherwise, find the quantity of the selection
              qty = bOptions[current].selections[currentSelected].qty
              # Now find the value of the selected option, and set priceInclTax
              selectedValue = bOptions[current].selections[currentSelected].name
              if qty > 1
                selectedValue += ' - Quantity: ' + qty
              PriceWaiter.setProductOption bOptions[current].title, selectedValue
          return
        # Reload the bundle's price, to pull the initial options into PriceWaiter
        if typeof bundle != 'undefined'
          bundle.reloadPrice()
        return

      handleGrouped = ->
        # Get the Grouped product table rows
        productTable = $$('table.grouped-items-table')[0]
        productRows = productTable.select('tbody')[0]
        productRows = productRows.childElements()
        # Prevent users from attempting to name a price on grouped products without
        # setting any quantities
        if productRows.length > 0
          PriceWaiter.setProductOptionRequired 'Quantity of Products', true
        for row of productRows
          if !isNaN(parseInt(row, 10))
            # Bind to the Quantity change
            productRows[row].select('input.qty')[0].observe 'change', (event) ->
              qty = @value
              # Find the name and price based on the input's product ID
              # The product ID is found in the input's name inside the square brackets
              pattern = /\[(.*)\]/
              inputName = @name
              productID = pattern.exec(inputName)
              productID = productID[1]
              productName = window.PriceWaiterGroupedProductInfo[productID][0]
              productPrice = window.PriceWaiterGroupedProductInfo[productID][1]
              # The user changed the quantity field. We need to find the previous quantity and price
              previousQuantity = PriceWaiter.getProductOptions()[productName + ' (' + productPrice + ')']
              amountToRemove = Number(previousQuantity * productPrice)
              if qty > 0
                # Entered a quantity, set product name as option name, add quantity as value
                PriceWaiter.setProductOption productName + ' (' + productPrice + ')', qty
                # Add the price to the product's total
                PriceWaiter.setPrice Number(PriceWaiter.getPrice()) + Number(productPrice * qty)
              else
                PriceWaiter.clearProductOption productName + ' (' + productPrice + ')'
              # If they previously had a quantity for this option, remove it from the total
              if previousQuantity > 0
                PriceWaiter.setPrice Number(PriceWaiter.getPrice() - amountToRemove)
              # Test if any Product Options are set. If they are, we can disable
              # our required option. Otherwise, ensure it is in place.
              if Object.keys(PriceWaiter.getProductOptions()).length > 0
                PriceWaiter.clearRequiredProductOptions()
              else
                PriceWaiter.setProductOptionRequired 'Quantity of Products', true
              return
        return

      PriceWaiter.setRegularPrice PriceWaiterRegularPrice
      # define indexof, needed for older versions of IE
      if !Array::indexof

        Array::indexof = (needle) ->
          i = 0
          while i < @length
            if @[i] == needle
              return i
            i++
          -1

      # define getElementsByRegex to find required bundle options

      document['getElementsByRegex'] = (pattern) ->
        arrElements = []
        # to accumulate matching elements
        re = new RegExp(pattern)
        # the regex to match with

        findRecursively = (aNode) ->
          # recursive function to traverse dom
          if !aNode
            return
          if aNode.id != undefined and aNode.id.search(re) != -1
            arrElements.push aNode
          # found one!
          for idx of aNode.childNodes
            findRecursively aNode.childNodes[idx]
          return

        findRecursively document
        # initiate recursive matching
        arrElements
        # return matching elements

      # Bind to Qty: input
      if $('qty') != null
        $('qty').observe 'change', ->
          PriceWaiter.setQuantity $('qty').value
          return
      switch PriceWaiterProductType
        when 'simple'
          handleSimples()
        when 'configurable'
          handleConfigurables()
        when 'bundle'
          handleBundles()
        when 'grouped'
          handleGrouped()
      return

  if window.PriceWaiterWidgetUrl
    do ->
      pw = document.createElement('script')
      pw.type = 'text/javascript'
      pw.src = window.PriceWaiterWidgetUrl
      pw.async = true
      s = document.getElementsByTagName('script')[0]
      s.parentNode.insertBefore pw, s
      return
  return
