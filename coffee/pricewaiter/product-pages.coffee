###
 * Copyright 2013-2015 Price Waiter, LLC
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
###

bootstrapPriceWaiter = ->

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

if window.addEventListener
    window.addEventListener 'DOMContentLoaded', bootstrapPriceWaiter, no
else
    window.attachEvent 'onload', bootstrapPriceWaiter
