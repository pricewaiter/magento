/*
 * Copyright 2013 Price Waiter, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */
$(document).observe('dom:loaded', function() {
	if (typeof adminPW == 'function') {
		adminPW();
	}
	PriceWaiterOptions.onload =
		function(PriceWaiter) {
			PriceWaiter.setRegularPrice(PriceWaiterRegularPrice);

			// define indexof, needed for older versions of IE
			if(!Array.prototype.indexof) {
				Array.prototype.indexof = function(needle) {
					for(var i = 0; i < this.length; i++) {
						if(this[i] === needle) {
							return i;
						}
					}
					return -1;
				};
			}

			// define getElementsByRegex to find required bundle options
			document['getElementsByRegex'] = function(pattern) {
				var arrElements = [];   // to accumulate matching elements
				var re = new RegExp(pattern);   // the regex to match with

				function findRecursively(aNode) { // recursive function to traverse dom
					if (!aNode)
						return;
					if (aNode.id !== undefined && aNode.id.search(re) != -1)
						arrElements.push(aNode);  // found one!
					for (var idx in aNode.childNodes) // search children...
						findRecursively(aNode.childNodes[idx]);
				}

				findRecursively(document); // initiate recursive matching
				return arrElements; // return matching elements
			};

			// Bind to Qty: input
			if ($('qty') !== null) {
				$('qty').observe('change', function(){
					PriceWaiter.setQuantity($('qty').value);
				});
			}

			switch(PriceWaiterProductType) {
				case 'simple':
				handleSimples();
				break;
				case 'configurable':
				handleConfigurables();
				break;
				case 'bundle':
				handleBundles();
				break;
				case 'grouped':
				handleGrouped();
				break;
			}

			function simplesSelect(select, name) {
				select.observe('change', function(){
					PriceWaiter.setProductOption(name, select.options[select.selectedIndex].text);
				});
			}

			function simplesInput(select, name) {
				if (select.type == "text" || select.tagName == 'TEXTAREA') {
					select.observe('change', function(){
						PriceWaiter.setProductOption(name, select.value);
					});
				} else {
					select.observe('change', function(){
						var optionValue = select.next('span').select('label')[0].innerHTML;
						optionValue = optionValue.replace(/\s*<span.*\/span>/, '');
						PriceWaiter.setProductOption(name, optionValue);
					});
				}
			}


			function handleSimples() {
				// if there are no custom options, we don't have anything to do
				if (typeof(opConfig) == 'undefined') {
					return;
				}

				// If this product has an upload file option, we can't use the NYP widget
				var productForm = $('product_addtocart_form');
				if (productForm.getInputs('file').length !== 0) {
					console.log("The PriceWaiter Name Your Price Widget does not support upload file options.");
					$$('div.name-your-price-widget').each(function(pww){
						pww.setStyle({display: 'none'});
					});
				}

				// Grab the updated price before opening the PriceWaiter window
				PriceWaiter.originalOpen = PriceWaiter.open;
				PriceWaiter.open = function() {
					var productPrice = 0;
					var priceElement = document.getElementsByRegex('^product-price-');
					var innerSpan = priceElement[0].select('span');
					if (typeof(innerSpan[0]) == 'undefined') {
						productPrice = priceElement[0].innerHTML;
					} else {
						productPrice = innerSpan[0].innerHTML;
					}
					PriceWaiter.setPrice(productPrice);
					PriceWaiter.originalOpen();
				};

				// Find the available options, and bind to them
				var productCustomOptions = $$('.product-custom-option');
				for (var current in productCustomOptions) {
					if (!isNaN(parseInt(current, 10))) {
						// Find the option label
						var optionLabel = productCustomOptions[current].up('dd').previous('dt').select('label')[0];
						var optionName = optionLabel.innerHTML.replace(/^<em.*\/em>/, '');
						// Check if this is a required option
						if (optionLabel.hasClassName('required')) {
							PriceWaiter.setProductOptionRequired(optionName);
						}
						// we have to handle different inputs a bit differently.
						switch (productCustomOptions[current].tagName) {
							case 'SELECT':
							simplesSelect(productCustomOptions[current], optionName);
							break;
							case 'INPUT':
							case 'TEXTAREA':
							simplesInput(productCustomOptions[current], optionName);
							break;
						}
					}
				}
			}

			function handleConfigurables() {
				// Bind to each configurable options 'change' event
				spConfig.settings.each(function(setting){
					var attributeId = $(setting).id;
					attributeId = attributeId.replace(/attribute/,'');
					var optionName = spConfig.config.attributes[attributeId].label;
					// If this option is required, tell the PriceWaiter widget about the requirement
					if ($(setting).hasClassName('required-entry') && (typeof PriceWaiter.setProductOptionRequired == 'function')) {
						PriceWaiter.setProductOptionRequired(optionName, true);
					}
					Event.observe(setting, 'change', function(event){
						// Update PriceWaiter's price and options when changes are made
						PriceWaiter.setPrice(Number(spConfig.config.basePrice) + Number(spConfig.reloadPrice()));
						var optionValue = setting.value !== "" ? setting.options[setting.selectedIndex].innerHTML : undefined;
						// if the option value is undefined, clear the option. Otherwise, set the newly selected option.
						if (optionValue === undefined) {
							PriceWaiter.clearProductOption(optionName);
						} else {
							PriceWaiter.setProductOption(optionName, optionValue);
						}
					});
				});
			}

			function handleBundles() {
				// Find options that are marked as required
				var requiredOptions = [];
				var bundleElements = document.getElementsByRegex('^bundle-option-');
				var rePattern = /\[(\d*)\]/;
				for (var bundleOption in bundleElements) {
					if (!isNaN(parseInt(bundleOption, 10))) {
						var obj = bundleElements[bundleOption];
						if (obj.hasClassName('required-entry') || obj.hasClassName('validate-one-required-by-name')) {
							var matched = rePattern.exec(obj.name);
							requiredOptions.push(parseInt(matched[1], 10));
						}
					}
				}
				requiredOptions = requiredOptions.uniq();

				// Add required Options to PriceWaiter
				for (var key in bundle.config.options) {
					if (requiredOptions.indexOf(parseInt(key, 10)) > -1) {
						var opt = bundle.config.options[key];
						PriceWaiter.setProductOptionRequired(opt.title, true);
					}
				}

				// Bind to event fired when price is changed on bundle
				document.observe("bundle:reload-price", function(event) {
					PriceWaiter.setPrice(event.memo.priceInclTax);
					var bSelected = event.memo.bundle.config.selected;
					var bOptions = event.memo.bundle.config.options;
					for (var current in bSelected) {
						// Find which value is selected
						var currentSelected = bSelected[current];
						if (currentSelected.length === 0) {
							// If none, unset the Product option
							PriceWaiter.clearProductOption(bOptions[current].title);
						} else {
							// Otherwise, find the quantity of the selection
							var qty = bOptions[current].selections[currentSelected].qty;
							// Now find the value of the selected option, and set priceInclTax
							var selectedValue = bOptions[current].selections[currentSelected].name;
							if (qty > 1) {
								selectedValue += " - Quantity: " + qty;
							}
							PriceWaiter.setProductOption(bOptions[current].title, selectedValue);
						}
					}
				});

				// Reload the bundle's price, to pull the initial options into PriceWaiter
				if (typeof(bundle) != 'undefined') {
					bundle.reloadPrice();
				}
			}

			function handleGrouped() {
				// Get the Grouped product table rows
				var productTable = $$('table.grouped-items-table')[0];
				var productRows = productTable.select('tbody')[0];
				productRows = productRows.childElements();

				for (var row in productRows) {
					if (!isNaN(parseInt(row, 10))) {
						// Bind to the Quantity change
						productRows[row].select('input.qty')[0].observe('change', function(event){
							var qty = this.value;
							// Get the Product's name
							var productName = this.up('tr').firstDescendant().innerHTML;
							// Get the Product's price
							var productPrice = this.up('tr').select('span.price')[0].innerHTML;
							// The user changed the quantity field. We need to find the previous quantity and price
							var previousQuantity = PriceWaiter.getProductOptions()[productName + " (" + productPrice + ")"];
							var amountToRemove = Number(previousQuantity * productPrice.substring(1));
							if (qty > 0) {
								// Entered a quantity, set product name as option name, add quantity as value
								PriceWaiter.setProductOption(productName + " (" + productPrice + ")", qty);
								// Add the price to the product's total
								PriceWaiter.setPrice(Number(PriceWaiter.getPrice()) + Number(productPrice.substring(1) * qty));
							} else {
								PriceWaiter.clearProductOption(productName + " (" + productPrice + ")");
							}
							// If they previously had a quantity for this option, remove it from the total
							if (previousQuantity > 0) {
								PriceWaiter.setPrice(Number(PriceWaiter.getPrice() - amountToRemove));
							}
						});
					}
				}
			}
		};

	(function() {
		var pw = document.createElement('script');
		pw.type = 'text/javascript';
		pw.src = window.PriceWaiterWidgetUrl;
		pw.async = true;

		var s = document.getElementsByTagName('script')[0];
		s.parentNode.insertBefore(pw, s);
    })();
});
