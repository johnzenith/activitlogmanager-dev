
# 'profile_update' action hook 

    - only fires when a user is editing another user
    - add functionality to ensure that 'own profile user' can trigger the event so that event aggregation can work

## '$wp_locale: use the WordPress date and time locale object: WP_Locale'

## Add an 'explain message' context to the generated event message

    - This will be used to explain the message so that the admin/user can understand better.
    for example: automatic system update, automatic option/setting update that was triggered.

## Add an admin/front-end page view controller

    - **For Admin**: hook into {menu hook on admin and check page url, admin screen} to do the admin page views
    - **For Front-end**: hook into {the init hook and check the page url, post type, as requested}

## Maybe add the page where the event is normally triggered when listing the event on the admin screen

    - For example: put the url to the page such as `/admin/options-general.php`, etc.

## When builing the search filters, ensure that the admins can filter the event log by specifying whether to filter the *event log count*, that is, filter to get all the event log type that exceeds a particular number count.

    - For example: get all the event types whose rows is greater or equal to 10.

## When displaying the logs, ensure that the admins can easily disable / control the log by clicking on an *edit/configure* log button which opens a modal:

    - If an event is disabled, show the disabled flag, on previous event log, in list table.
    - The modal will allow the admin user to easily disable/enable the event
    - Disable/enable notifications.

## When updating the user profile, the [profile_update] action is fired. Also check whether event data aggregatiion is turned on, and specify actions that must be called even when doing aggregation.

    - For example if only the user role is updated, don't called the profile update action hook.