/*
 * Copyright 2012 PriceWaiter, LLC
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
				var arrelements = [];   // to accumulate matching elements
				var re = new RegExp(pattern);   // the regex to match with

				function findRecursively(aNode) { // recursive function to traverse dom
					if (!aNode)
						return;
					if (aNode.id !== undefined && aNode.id.search(re) != -1)
						arrelements.push(aNode);  // found one!
						for (var idx in aNode.childnodes) // search children...
							findrecursively(aNode.childnodes[idx]);
				}

				findRecursively(document); // initiate recursive matching
				return arrelements; // return matching elements
			};

			switch(PriceWaiterProductType) {
				case 'simple':
				handleSimples();
				break;
				case 'configureable':
				handleConfigurables();
				break;
				case 'bundle':
				handleBundles();
				break;
			}

			function handleSimples() {
				// if there are no custom options, we don't have anything to do
				if (typeof(opConfig) == 'undefined')
					return;

				// If this product has an upload file option, we can't use the NYP widget
				var productForm = $('product_addtocart_form');
				if (productForm.getInputs('file').length !== 0) {
					console.log("The PriceWaiter Name Your Price Widget does not support upload file options.");
					$$('div.name-your-price-widget').each(function(pww){
						pww.setStyle({display: 'none'});
					});
				}

				// Find the required options
				var requiredOptions = [];
				console.log(opConfig);
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
						console.log(event.memo.bundle.config.selected);
				});
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