/**
 * Admin JS 
 *
 * @since  1.0
 */
"strict";

(function( $, document, undefined ) {
    var Handler    = {},
        pageWindow = $(window);

    /* Track unsafe form data */
    Handler.trackUnsavedFormData = function()
    {
        var fieldElem,
            getForm = $('#stackauth-meta-settings');

        // Track the form data unsaved state when any change is made
        getForm
        .on('input', function(e)
        {
            fieldElem = $(e.target);

            if ( fieldElem.hasClass('stackauth-ignore-tracking') ) {
                pageWindow.unbind('beforeunload');
            } else {
                pageWindow.on( 'beforeunload', function(e) {
                    return StackAuthAdmin.unsaved_settings_text.toString();
                });
            }
        })
        .on('submit', function()
        {
            pageWindow.unbind('beforeunload');
        });
    };

    Handler.toggle = function( elem ) {
        if ( elem.is( ':hidden' ) ) {
            elem.slideDown( 400 ).show( /* Make sure it is visible */ );
        }
        else {
            elem.slideUp( 400 ).hide( /* Make sure it is hidden */ );
        }
    };

    Handler.displayResetBtn = function( elem )
    {
        var noBackupResetBtn   = $('.stackauth-reset-btn:not(.stackauth-has-backup-btn)'),
            hashBackupResetBtn = $('.stackauth-has-backup-btn');

        if ( elem.is(':checked' ) ) {
            noBackupResetBtn.slideUp(100);
            hashBackupResetBtn.slideDown(100);
        } else {
            hashBackupResetBtn.slideUp(100);
            noBackupResetBtn.slideDown(100);
        }
    };

    Handler.checkbox = function( elem )
    {
        var ignoreValue = ( typeof elem.attr('data-ignore-value') !== 'undefined' );

        // Don't toggle when field is disabled or readonly
        if ( typeof elem.attr( 'disabled' ) !== 'undefined' 
        || typeof elem.attr( 'readonly') !== 'undefined' )
        {
            // do nothing
        } else {
            if ( 'radio' === elem.attr('type') ) {
                if ( ! elem.is( ':checked' ) ) {
                    elem.attr('checked', true);
                }
            } else {
                if ( elem.is( ':checked' ) ) {
                    if ( ignoreValue ) {
                        elem.attr('checked', false);
                    } else {
                        elem.val( 0 ).attr('checked', false);
                    }
                } else {
                    if ( ignoreValue ) {
                        elem.attr('checked', true);
                    } else {
                        elem.val( 1 ).attr('checked', true);
                    }
                }
            }
        }
    };

    // Highlight the sub menu admin items from current page
    Handler.highlightAdminMenu = function() {
        var i,
            menu,
            menuLink,
            isActiveMenu,
            currentTab  = '', // Set the current tab here
            menus       = $('.wp-has-current-submenu.wp-menu-open.toplevel_page_stackauth .wp-submenu.wp-submenu-wrap li > a');

        for ( i = 0; i < menus.length; i++ ) {
            menu         = $( menus[i] );
            menuLink     = menu.attr('href');
            isActiveMenu = menuLink.match( currentTab );

            if ( isActiveMenu 
            && typeof isActiveMenu[0] !== 'undefined' 
            && isActiveMenu[0].length > 5 ) {
                menu.addClass('stackauth-active-sub-menu');
                // console.log(menu);
            }
        }
    };

    /* Main */
    Handler.trackUnsavedFormData();
    Handler.highlightAdminMenu();

    // Post Box Toggle
    $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
    postboxes.add_postbox_toggles( StackAuthAdmin.page_hook_suffix.toString() );

    // Disable the settings form submit action when enter key is pressed
    $('#stackauth-meta-settings input').on( 'keyup', function (e) {
        if ( 'enter' === e.key.toLowerCase() ) {
            e.preventDefault();
            return false;
        }
    });
    
    /* Show post box option */
    $('.stackauth-show-postbox-label').on('click', function (e)
    {
        e.preventDefault();
        var elem        = $(this),
            elemSibling = elem.siblings('.hide-if-js');

        if ( elemSibling.is( ':hidden' ) ) {
            elem.siblings('.hide-if-js').slideDown( 400 );
        }
        elem.hide();
    });

    /* Hide post box option */
    $('.stackauth-hide-postbox-label').on('click', function (e)
    {
        e.preventDefault();
        var elem       = $(this),
            elemParent = elem.parents('.hide-if-js');
        
        if ( elemParent.is( ':visible' ) ) {                
            elemParent.slideUp( 400 );
        }
        elemParent.siblings('.stackauth-show-postbox-label').show();
    });

    /* Toggle the post box option */
    $('.stackauth-toggle-postbox-label').on('click', function (e)
    {
        e.preventDefault();
        var me   = $(this),
            elem = me.siblings('.hide-if-js');

        Handler.toggle( elem );

        if ( elem.is( ':visible' ) ) {
            me.addClass( 'is-visible' );
        } else {
            me.removeClass( 'is-visible' );
        }
    });

    $('.hide-if-js').children('.stackauth-toggle-postbox-label').on('click', function (e)
    {
        e.preventDefault();
        var me      = $(this),
            elem    = me.parents('.hide-if-js'),
            toggler = elem.siblings( '.stackauth-toggle-postbox-label' );

        Handler.toggle( elem );

        if ( elem.is( ':visible' ) ) {
            toggler.addClass( 'is-visible' );
        } else {
            toggler.removeClass( 'is-visible' );
        }
    });

    // Set input checkbox value
    $('.stackauth-wrap input[type="checkbox"], .stackauth-wrap input[type="radio"]').on('click', function ()
    {
        var elem     = this;
        getValue     = Number.parseInt( elem.value ),
        isRealNumber = isNaN( getValue );

        if ( $(elem).is( ':checked' ) ) {
            // Don't set the input value if it's not 1 or 0
            if ( isRealNumber && ( getValue === 1 || getValue === 0 ) ) {
                elem.value = 1;
            }
            elem.setAttribute( 'checked', true );
        }
        else {
            // Don't set the input value if it's not 1 or 0
            if ( isRealNumber && ( getValue === 1 || getValue === 0 ) ) {
                elem.value = 0;
            }
            elem.removeAttribute( 'checked' );
        }
    });

    // Settings field toggle switch
    $('.stackauth-settings-switch').on('click', function ()
    {
        var elem          = $(this),
            settingsField = elem.siblings('.stackauth-input-field');
        if ( 'label' !== elem.prop('tagName').toLowerCase() ) {
            Handler.checkbox( settingsField );
        }
    });

    // Installed Extensions checkbox handler
    $( '.enabled-extensions-field' ).on( 'click', function ()
    {
        var elem      = $(this),
            extension = elem.parents( 'td.enable' ).siblings( 'th' ).children( 'input' );

        if ( elem.is( ':checked' ) ) {
            extension.attr( 'checked', true );
        } else {
            extension.attr( 'checked', false );
        }
    });

    $( 'td.enable' ).siblings( 'th' ).children( 'input' ).on('click', function( e ) 
    {
        var elem      = $(this),
            extension = elem.parent().siblings( '.enable' ).children().find( 'input' );

        if ( elem.is( ':checked' ) ) {
            extension.attr( 'checked', true );
        } else {
            extension.attr( 'checked', false );
        }
    });

    // Set the reset link
    $('#stackauth-backup-settings').on('click', function()
    {
        Handler.displayResetBtn( $(this) );
    });

    // Installed Extensions list table select all checkbox controller
    $('#cb-select-all-1, #cb-select-all-2').on('click', function()
    {
        var installedExtensions = $('input[name="stackauth-enabled-extensions[]"]'),
            cb                  = $(this);

        if ( cb.is( ':checked' ) ) {
            installedExtensions.val( 1 );
        } else {
            installedExtensions.val( 0 );
        }
        // console.log( installedExtensions );
    });

    $('.stackauth-current-loaded-draft-cb').parents('tr').addClass('stackauth-current-loaded-draft-tr');
})( jQuery, document, undefined );