# MAIN
    - Only use the [term-events] when on super mode
    - Use Specific event [category, tag] on simple mode

    ----------------------------------------------------

    - [User Events]
    - Simplified the result too

    -----------------------------------------------------
    - [Widgets]
    - [Nav Menu]


-------------------------------------
# Statistics

1. Each log should have a statistics
2. when a log is inserted, update the log statistics
3. when a log is deleted, update the statistics as well
-----------------------------------------



# 1.
**Verify the menu pages**
    Check base controller PluginFactory::isPluginMenuPage()

# 2.
**Search and replace**
    pattern: @(return|param)([ ]+)\((.*)\)
    replace: @$1$2$3

# 3.
**Add the alert handler to the PHPError class**

# 4. 
**check whether the internal ip address can be filtered**
    ip-factory.php

# 5.
-- Install the plugin global settings
-- installer#L87

# 6.
-- Hook into the 'add_user_role', 'update_user_role', 'remove_user_role' action hooks, and look whether the 'is_updating_user_cap' is set to true, then re-update the 'capability' title field to 'role'.

# 7.
On multisite when viewing the wp-activate.php page file, all plugins are disabled temporary because the WP_INSTALLING constant was defined at the top of the wp-activate.php file. So whenever a new user is confirming their account by email, this prevent plugins from running and listening to important events such as wpmu_new_user, add_user_to_blog, and wp_activate.
[>>>>>>>>>>>> use the  wpmu_activate_signup() to mitigate this <<<<<<<<<<<<<<<<<<<<]