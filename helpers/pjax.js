// pjax <?php
/**
 * PJAX jQuery Plugin v2.2, by Olivier St-Laurent
 *
 * This plugin makes any 'a' or 'form' to work with AJAX calls instead of reloading all pages.
 * No need to do anything in particular in the HTML code.
 * After activating it, it will work automatically and will fallback to normal behavior if not supported.
 * The important thing is to make sure that all bindings are made in their own page and no document.ready is used.
 *
 * Very simple to activate, add this line in a script included at the end of every page :
 *
 *      $('a, form').pjax();
 *
 * You can also activate it automatically using one simple line of code in the header :
 *
 *      $.pjaxAutoBind();
 *
 *
 *  You can define more specific elements to activate pjax on
 *      for example : Activating PJAX for all 'a' and 'form' elements that have the 'pjax' class :
 *
 *          $('a.pjax, form.pjax').pjax();
 *            // or
 *          $.pjaxAutoBind('a.pjax', 'form.pjax');
 *
 *
 *  You can also define specific default parameters for the pjax behavior
 *
 *      by using parameters in the activation line :
 *          $('a, form').pjax({container: 'body', timeout: 2000});
 *            // or
 *          $.pjaxAutoBind({container: 'body', timeout: 2000});
 *            // or
 *          $.pjaxAutoBind('a', 'form', {container: 'body', timeout: 2000});
 *
 *      or by overriding the PjaxDefaultOptions Object after including the PJAX Script :
 *          PjaxDefaultOptions.container = 'body';
 *          PjaxDefaultOptions.timeout = 2000;
 *
 *
 *  You may also want to bind events to the PJAX Loading process :
 *      PjaxDefaultOptions.onSuccess = function(event, data, status, xhr, options){
 *          console.log('Success !');
 *      };
 *  Possible events to bind, in the same order as they are called :
 *      onBeforeSend(e, xhr, options) // return false to cancel the request
 *      onStart(e, xhr, options) // Called just before sending the request if it was not cancelled by 'onBeforeSend'
 *      onTimeout(e, xhr, options) // Called if the request timed out. Return false to abort the request, then onError.
 *      onError(e, xhr, textstatus, errorThrown, options, finalUrl, title) // Called when an error occurs
 *      onSuccess(e, responseContent, status, xhr, options, finalUrl) // When the request was successful and we have the response
 *      onComplete(e, xhr, textstatus, options, finalUrl) // When request is completed, regardless if there was an error or not
 *
 *
 *  You may want to call directly a pjax via Javascript, by using the pjax() function.
 *  This function will automatically fallback to a normal reload behavior if pjax is not supported.
 *  Ex:
 *      pjax('url', options); // Options param is optional, it will take the default options.
 *
 *      pjax(); // Simply reload the current page, using pjax
 *
 *
 *  Optional HTML attributes for every element (A or FORM) :
 *      data-pjax-disabled : If set to true, disable pjax for this element, use normal behavior instead.
 *      data-pjax-container : Set a specific container to put the response contents in (jQuery Selector)
 *      data-pjax-fragment : Set a specific fragment to fetch from the response contents (jQuery Selector)
 *      data-pjax-nopush : If set to true, the url will not be pushed in the browser's history
 *      data-pjax-timeout : Set a specific timeout in milliseconds for this request.
 *                          This will also override the onTimeout function and abort the request if timed out.
 *      data-pjax-error : Action to do when there is an error in the request [ abort | retry | reload ]
 *                        This will override the onError function and do the specified action instead.
 *                        If set to Retry, it will also remove the timeout for this retry.
 *                        If set to Reload, the requested page will be loaded completely instead of using PJAX.
 *
 *
 *  Server Side PHP, In the main layout :
 *
 *      // this makes redirects ( header("Location: ...") ) to push the new redirected url in the browser's history
 *      header("X-FinalURL: ".URL);
 *
 *      // For content output
 *      if (PJAX) { // PJAX = !empty($_SERVER['HTTP_X_PJAX'])
 *          // Output only the page contents and inline scripts, including the page's bindings and the pjax activation
 *      } else {
 *          // Output the whole HTML page
 *      }
 *
 *
 *///?>

var PjaxDefaultOptions = {
    url : '',
    type : 'GET', // POST | GET
    data : {},
    container : 'body', // Set a specific container to put the response contents in (jQuery Selector)

    fragment : null, // Set a specific fragment to fetch from the response contents (jQuery Selector)

    timeout : 2000, // Set a specific timeout in milliseconds for this request. This is a soft timeout.

    push : true, // If set to true, it will replace the address bar and push the new url in the browser's history
    // The Next/Prev buttons will work as expected.

    replace : false, // If set to true, only replaces the address bar and does not push the url to the history.
    //This overrides the push option.

    dataType : 'html',
    onBeforeSend : function(e, xhr, options) {
    },
    onStart : function(e, xhr, options) {
    },
    onTimeout : function(e, xhr, options) {
    },
    onError : function(e, xhr, textstatus, errorThrown, options, finalUrl, title) {
    },
    onSuccess : function(e, responseContent, status, xhr, options, finalUrl) {
    },
    onComplete : function(e, xhr, textstatus, options, finalUrl) {
    },
    statusCode : {// Actions to do for any status code
        404 : function() {
            // Page Not Found
        }
    },
    debug: false,
};

if (typeof (jQuery) === 'function') {
    (function($) {

        // Pjax Supported ?
        $.support.pjax =
            window.history && window.history.pushState && window.history.replaceState
            // pushState isn't reliable on iOS until 5.
            && !navigator.userAgent.match(/((iPod|iPhone|iPad).+\bOS\s+[1-4]|WebApps\/.+CFNetwork)/);

        $.pjaxAutoBind = function(selectorA, selectorForm, options, bindOn) {

            // Fall back to normal behavior for older browsers.
            if (!$.support.pjax) {
                return;
            }

            if (typeof selectorA === 'object') {
                options = selectorA;
                selectorA = undefined;
            }

            if (typeof options === 'undefined') {
                options = {};
            } else if (typeof options === 'string') {
                options = {container : options};
            }

            if (selectorA === undefined) {
                selectorA = 'a';
            }

            if (selectorForm === undefined) {
                selectorForm = 'form';
            }

            if (bindOn === undefined) {
                bindOn = 'html';
            }

            $(bindOn).on('click', selectorA, function(event) {
                return handle_A(event, options);
            });

            $(bindOn).on('submit', selectorForm, function(event) {
                return handle_Form(event, options);
            });
        };

        $.fn.pjax = function(options) {

            // Fall back to normal behavior for older browsers.
            if (!$.support.pjax) {
                return;
            }

            if (typeof options === 'undefined') {
                options = {};
            } else if (typeof options === 'string') {
                options = {container : options};
            }

            $(this).filter('a').click(function(event) {
                return handle_A(event, options);
            });

            $(this).filter('form').submit(function(event) {
                return handle_Form(event, options);
            });

        };

        function getOptionsFromAttributes(options, elem)
        {
            options.container =
                $(elem).attr('data-pjax-container')
                || options.container
            ;
            options.fragment =
                $(elem).attr('data-pjax-fragment')
                || options.fragment
            ;
            if ($(elem).attr('data-pjax-timeout')) {
                options.timeout = $(elem).attr('data-pjax-timeout');
                options.onTimeout = function() {
                    return false;
                };
            }

            if ($(elem).attr('data-pjax-error')) {
                options.onError = $(elem).attr('data-pjax-error');
            }

            return options;
        }

        function bindEvents(options)
        {
            $(options.container).off('pjax:beforeSend');
            $(options.container).on(
                'pjax:beforeSend', (options.onBeforeSend || function() {
                }));
            $(options.container).off('pjax:start');
            $(options.container).on(
                'pjax:start', (options.onStart || function() {
                }));
            $(options.container).off('pjax:timeout');
            $(options.container).on(
                'pjax:timeout', (options.onTimeout || function() {
                }));
            $(options.container).off('pjax:error');
            $(options.container).on(
                'pjax:error', (options.onError || function() {
                }));
            $(options.container).off('pjax:success');
            $(options.container).on(
                'pjax:success', (options.onSuccess || function() {
                }));
            $(options.container).off('pjax:complete');
            $(options.container).on(
                'pjax:complete', (options.onComplete || function() {
                }));
        }

        function handle_A(event, options)
        {
            if (event.isDefaultPrevented() || (event.originalEvent && event.originalEvent.defaultPrevented)) {
                event.preventDefault();
                return false;
            }

            var options = $.extend({}, PjaxDefaultOptions, options);
            var link = event.currentTarget;

            // Fallback to normal behavior in some conditions :

            // href="#"
            if (link.href === location.href + '#') {
                return;
            }

            // Same location with an anchor
            if (link.hash && link.href.replace(link.hash, '') === location.href.replace(location.hash, '')) {
                return;
            }

            // Ctrl+Click or Middle Click
            if (event.which > 1 || event.metaKey) {
                return;
            }

            // target="_blank"
            if (link.target !== '') {
                return;
            }

            // Pjax disabled for this element
            if ($(link).attr('data-pjax-disabled')) {
                return;
            }

            // Cross origin, or javascript:...
            if (location.protocol !== link.protocol || location.host !== link.host) {
                return;
            }

            options.url = link.href;
            options.target = link;
            if ($(link).attr('data-pjax-nopush')) {
                options.push = false;
            }

            options = getOptionsFromAttributes(options, link);

            $.pjax(options);

            event.preventDefault();
            return false;
        }

        function handle_Form(event, options)
        {
            if (event.isDefaultPrevented() || (event.originalEvent && event.originalEvent.defaultPrevented)) {
                event.preventDefault();
                return false;
            }

            var options = $.extend({}, PjaxDefaultOptions, options);
            var form = event.currentTarget;
            var a = parseURL(form.action);

            // Fallback to normal behavior in some conditions :

            // target="_blank"
            if (form.target !== '') {
                return;
            }

            // Pjax disabled for this element
            if ($(form).attr('data-pjax-disabled')) {
                return;
            }

            // Cross origin, or javascript:...
            if (location.protocol !== a.protocol || location.host !== a.host) {
                return;
            }

            options.data = formValuesToArray(form);
            options.type = form.method || 'get';
            options.url = a.href;
            options.target = form;
            if ($(form).attr('data-pjax-nopush')) {
                options.push = false;
            }

            options = getOptionsFromAttributes(options, form);

            $.pjax(options);

            event.preventDefault();
            return false;
        }

        var pjax = $.pjax = function(options) {
            var options = $.extend(true, {}, $.ajaxSettings, PjaxDefaultOptions, options);
            options.url = parseURL(options.url).href;

            if (typeof(options.onError) == 'string') {
                switch (options.onError.toLowerCase()) {
                    case 'abort' :
                        options.onError = function (e, xhr, textstatus, errorThrown, settings, finalUrl, title) {
                            return false;
                        };
                        break;
                    case 'retry' :
                        options.onError = function (e, xhr, textstatus, errorThrown, settings, finalUrl, title) {
                            var settings = {
                                url: settings.url,
                                container: settings.container,
                                fragment: settings.fragment,
                                push: settings.push,
                                replace: settings.replace,
                                dataType: settings.dataType,
                                type: settings.type,
                                data: settings.data,
                                timeout: 0,
                                onError: function () {
                                },
                                onBeforeSend: settings.onBeforeSend,
                                onStart: settings.onStart,
                                onTimeout: settings.onTimeout,
                                onSuccess: settings.onSuccess,
                                onComplete: settings.onComplete,
                                statusCode: settings.statusCode
                            };
                            $.pjax(settings);
                        };
                        break;
                    case 'reload' :
                        options.onError = function (e, xhr, textstatus, errorThrown, settings, finalUrl, title) {
                            window.location = settings.url;
                        };
                        break;
                    case 'continue404_or_reload' :
                        options.onError = function (e, xhr, textstatus, errorThrown, settings, finalUrl, title) {
                            if (xhr.status == 404) {
                                document.title = title;
                                var state = {
                                    pjax: true,
                                    finalUrl : finalUrl,
                                    replaceStateNextTime: true,
                                };
                                if (settings.debug) {
                                    console.log("PushState(404) " + finalUrl);
                                    console.log(state);
                                }
                                if (history.state.replaceStateNextTime) window.history.replaceState(state, title, finalUrl);
                                else window.history.pushState(state, title, finalUrl);
                                $(PjaxDefaultOptions.container).html(xhr.responseText);
                            } else {
                                window.location = finalUrl;
                            }
                        };
                        break;
                }
            }

            bindEvents(options);

            var context = options.context = $(options.container);
            var target = options.target;
            var url = options.url;
            var hash = parseURL(url).hash;

            function fire(type, args)
            {
                var event = $.Event(type, {relatedTarget : target});
                context.trigger(event, args);
                return !event.isDefaultPrevented();
            }

            var timeoutTimer;

            options.beforeSend = function(xhr, settings) {
                url = settings.url;
                if (settings.timeout > 0) {
                    timeoutTimer = setTimeout(function() {
                        if (!fire('pjax:timeout', [xhr, options])) {
                            xhr.abort('timeout');
                        }
                    }, settings.timeout);
                    settings.timeout = 0;
                }

                if (!options['noPjaxHeader']) {
                    xhr.setRequestHeader('X-PJAX', 'true');
                }

                if (!fire('pjax:beforeSend', [xhr, settings])) {
                    return false;
                }

                fire('pjax:start', [xhr, options]);
            };

            options.error = function(xhr, textStatus, errorThrown) {
                url = options['urlAddressBar'] || xhr.getResponseHeader('X-FinalURL') || url;
                fire('pjax:error', [xhr, textStatus, errorThrown, options, url, xhr.getResponseHeader('X-Title') || document.title]);
            };

            options.success = function(data, status, xhr) {
                url = options['urlAddressBar'] || xhr.getResponseHeader('X-FinalURL') || url;

                var title, oldTitle = document.title;

                if (options.fragment) {
                    // If they specified a fragment, look for it in the response and pull it out.
                    var html = $('<html>').html(data);
                    var $fragment = html.find(options.fragment);
                    if ($fragment.length) {
                        this.html($fragment.contents());
                        // If there's a <title> tag in the response, use it as the page's title.
                        title = xhr.getResponseHeader('X-Title') || html.find('title').text() || $fragment.attr('title') || $fragment.data('title');
                    } else {
                        return window.location = url;
                    }
                } else {
                    // If we got no data or an entire web page, go directly to the page and let normal error handling happen.
                    if (!$.trim(data) || /^\s*((<!DOCTYPE)|(<html))/i.test(data)) {
                        return window.location = url;
                    }
                    this.html(data);
                    title = xhr.getResponseHeader('X-Title') || this.find('title').text();
                }

                if (title) {
                    document.title = $.trim(title);
                }

                var state = {
                    pjax: true,
                    finalUrl : url,
                    container : options.container,
                    fragment : options.fragment,
                    timeout : options.timeout
                };

                if (options.replace) {
                    pjax.active = true;
                    if (options.debug) {
                        console.log("ReplaceState " + url);
                        console.log(state);
                    }
                    window.history.replaceState(state, document.title, url);
                } else if (options.push) {
                    // this extra replaceState before first push ensures good back button behavior
                    if (!pjax.active) {
                        window.history.replaceState($.extend({}, state, {finalUrl : null}), oldTitle);
                        pjax.active = true;
                    }
                    if (options.debug) {
                        console.log("PushState " + url);
                        console.log(state);
                    }
                    window.history.pushState(state, document.title, url);
                }

                // Google Analytics support
                if ((options.replace || options.push)) {
                    if (window._gaq) _gaq.push(['_trackPageview']);
                    else if (window.ga) ga('send', 'pageview', {page : url, title: title});
                }

                // If the URL has a hash in it, make sure the browser knows to navigate to the hash.
                if (hash !== '') {
                    window.location.href = hash;
                }

                fire('pjax:success', [data, status, xhr, options, url]);
            };

            options.complete = function(xhr, textStatus) {
                url = options['urlAddressBar'] || xhr.getResponseHeader('X-FinalURL') || url;
                if (timeoutTimer) {
                    clearTimeout(timeoutTimer);
                }
                fire('pjax:complete', [xhr, textStatus, options, url]);
            };

            // Cancel the current request if we're already pjaxing
            var xhr = pjax.xhr;
            if (xhr && xhr.readyState < 4) {
                xhr.onreadystatechange = $.noop;
                xhr.abort();
            }

            pjax.options = options;
            pjax.xhr = $.ajax(options);
            $(document).trigger('pjax', [pjax.xhr, options]);
            return pjax.xhr;
        };

        function parseURL(url)
        {
            var a = document.createElement('a');
            a.href = url;
            return a;
        }

        function formValuesToArray(form)
        {
            var formData = new Object();
            var elems = form.elements || ($(form)[0] || document.createElement('form')).elements;
            for (var i = 0; i < elems.length; i++) {
                if (
                    (elems[i].type != 'radio' && elems[i].type != 'checkbox')
                    || (
                        (elems[i].type == 'radio' || elems[i].type == 'checkbox')
                        && elems[i].checked == true
                    )
                ) {
                    if (elems[i].name.match(/\[\]$/g)) {
                        //If multiple inputs are named with empty bracket (name="elem[]"), they will be put in an array for themselves
                        var nameNoBracket = elems[i].name.replace('[]', '');
                        if (typeof formData[nameNoBracket] === "undefined"
                            || !(formData[nameNoBracket] instanceof Array)) {
                            formData[nameNoBracket] = new Array();
                        }
                        if ($(elems[i]).attr('multiple')) { // Support for <select multiple>
                            for (var y in $(elems[i]).val()) {
                                formData[nameNoBracket].push($(elems[i]).val()[y]);
                            }
                        } else {
                            formData[nameNoBracket].push(elems[i].value);
                        }
                    } else {
                        formData[elems[i].name] = elems[i].value;
                    }
                }
            }
            return formData;
        }

        // popstate Bindings
        var popped = ('state' in window.history), initialURL = location.href;
        $(window).bind('popstate', function(event) {
            // Ignore inital popstate that some browsers fire on page load
            var initialPop = (!popped && location.href === initialURL);
            popped = true;
            if (initialPop) {
                return;
            }
            var state = history.state;
            if (PjaxDefaultOptions.debug) {
                console.log("PopState " + location.href);
                console.log(state);
            }
            if (state && state.pjax) {
                var container = state.container || PjaxDefaultOptions.container;
                if ($(container).length) {
                    $.pjax($.extend({}, PjaxDefaultOptions, {
                        url : state.finalUrl || location.href,
                        fragment : state.fragment || PjaxDefaultOptions.fragment,
                        container : container,
                        push : false,
                        timeout : state.timeout || PjaxDefaultOptions.timeout,
                    }));
                } else {
                    window.location = location.href;
                }
            }
        });

        $.ajaxSetup({
            cache : true
        });

    })(jQuery);
} else {
    console.log('jQuery is not included. Therefore PJAX is disabled.');
}

function pjax(url, options) {
    if (url === undefined) {
        url = '';
    }
    if (options === undefined) {
        options = {url : url, container : 'body'};
    } else if (typeof (options) === 'string') {
        options = {url : url, container : options};
    }
    if (options.url === undefined) {
        options.url = url;
    }
    if (typeof (jQuery) === 'function' && typeof (jQuery.pjax) === 'function' && jQuery.support.pjax) {
        jQuery.pjax(options);
    } else {
        // Fallback to normal behavior if PJAX is disabled or not available
        if (typeof (jQuery) === 'function' && options['data']) {

            var url = options.url,
                method = options.type ? options.type.toUpperCase() : 'GET';
            var form = jQuery('<form>', {
                method : method === 'GET' ? 'GET' : 'POST',
                action : url,
                style : 'display:none'
            });
            if (method !== 'GET' && method !== 'POST') {
                form.append(jQuery('<input>', {
                    type : 'hidden',
                    name : '_method',
                    value : method.toLowerCase()
                }));
            }

            var data = options.data;
            if (typeof data === 'string') {
                jQuery.each(data.split('&'), function(index, value) {
                    var pair = value.split('=');
                    form.append(jQuery('<input>', {type : 'hidden', name : pair[0], value : pair[1]}));
                });
            } else if (typeof data === 'object') {
                for (key in data)
                    form.append(jQuery('<input>', {type : 'hidden', name : key, value : data[key]}));
            }

            jQuery(document.body).append(form);
            form.submit();
        } else {
            window.location = url;
        }
    }
}
