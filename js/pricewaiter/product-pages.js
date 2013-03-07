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

			switch(PriceWaiterProductType) {
				case 'simple':
				break;
				case 'configureable':
				handleConfigurables();
				break;
				case 'bundle':
				handleBundles();
				break;
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
				if(!Array.prototype.indexOf) {
					Array.prototype.indexOf = function(needle) {
						for(var i = 0; i < this.length; i++) {
							if(this[i] === needle) {
								return i;
							}
						}
						return -1;
					};
				}

				document['getElementsByRegex'] = function(pattern){
					var arrElements = [];   // to accumulate matching elements
					var re = new RegExp(pattern);   // the regex to match with

					function findRecursively(aNode) { // recursive function to traverse DOM
						if (!aNode)
							return;
						if (aNode.id !== undefined && aNode.id.search(re) != -1)
							arrElements.push(aNode);  // FOUND ONE!
							for (var idx in aNode.childNodes) // search children...
								findRecursively(aNode.childNodes[idx]);
					}

					findRecursively(document); // initiate recursive matching
					return arrElements; // return matching elements
				};

				// Bind to event fired when price is changed on bundle
				document.observe("bundle:reload-price", function(event) {
						PriceWaiter.setPrice(event.memo.priceInclTax);
						console.log(event.memo.bundle.config.selected);
				});

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

	}
);