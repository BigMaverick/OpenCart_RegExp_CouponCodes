+=========================================================+
|     RegExp COUPONS v. 1.0                               | 
|     http://juanmatiasdelacamara.wordpress.com/          |
|     juanmatias@gmail.com                                |
+=========================================================+

This is a modification to the OpenCart core files to allow coupons codes that fit in a regular expression rather in a fixed code.
Tested on OpenCart Version 1.5.3.1

-------------------------------------------------------------------------------


I did this modification because I needed it. So use it at your own risk and may the Force be with you. ;)

HOW TO INSTALL
--------------

Before: MAKE BUCKUP!

Just copy the folders inside upload to your OpenCart dir and run the following DB modifications (keep in mind the table prefix):
Table "coupon":
	ALTER TABLE `coupon` ADD `code_regexp` tinyint(4) NOT NULL ;
	ALTER TABLE `coupon` ADD `codesecurity` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL ;
	ALTER TABLE `coupon` ADD `codesecurityname` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_bin NULL ;
	ALTER TABLE `coupon` ADD `codename` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_bin NULL ;
	ALTER TABLE `coupon` CHANGE `code` `code` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL ;
Table "coupon_history"
	ALTER TABLE `oc_coupon_history` ADD `code` VARCHAR( 10 ) CHARACTER SET utf8 COLLATE utf8_bin NULL 


BE CAREFUL: this modifies core files. If you have made modifications to them, see the change log and apply it by hand. (The diff is made with GIT on the basis of standar installation of OpenCart.


WHAT'S THIS ABOUT?
------------------

This modification improves the coupon functionality.
At least in Argentina we have coupons groupes into campaigns. 
What's this mean?
For these coupons you wont have a fixed code (for example IP555 or 23DF43), instead you will have a range of values that match into a regular expression. (for example: codes starting with letters 'F' and 'B', then with 5 digits... this match /(f|F)(b|B)\d{5}/)
This set of coupons is called a campaign.

Each coupon inside a campaign can be used just once. This is due to the client buy the coupon in a dealer and then buy your product with the coupon. That's all.

If you want to see some coupon companies in Argentina: Cupónica, Groupon, Pez Urbano...

HOW DOES IT WORK?
-----------------

ADMIN

In first place this modification adds four four fields to coupon table and one to coupon_history.

So when you add a new coupon you will see:
	"Is a regular expression" : it flags if this coupon should be handled inside these modifications
	"Code name": Since each company calls its codes in its own ways, you can set the field name here (or let it empty for default)
	"Security code": Some companies complement the code with a security check, this is it (you can leave it empty if there's not sec code)
	"Security code name": Same as code name.

	NOTE: The uses per user and uses by coupon fields wont have effect when reg exp is selected.

USER

When the user select to add a coupon will se a select showing all available campaigns.

	NOTE: if two campaigns have the same name and field names will be shown as one. Then the system will try to fit the code in the correct reg exp.

If the coupon the user is trying to use is a standar one, then he/she can leave the select and insert the code directly.

If the coupon is a reg exp one the user must select a campaign. According to the campaign properties the sec code will be shown and the names will be changed.
From here on is all the same way than before.

