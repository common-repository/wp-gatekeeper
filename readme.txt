=== WP-Gatekeeper ===
Tags: spam, defense
Contributors: EricMeyer

WP-Gatekeeper adds an easily-answered challenge to your comment form.  This defeats automated comment spamming.  You can add your own challenges through the management interface.

== Installation ==

1. Upload to your plugins folder, usually `wp-content/plugins/`.
2. Activate the plugin on the plugin screen.

= All right, I installed it.  Now what? =

Load up the "Gatekeeper" management page under "Manage" and edit the default challenge, add some of your own, or both.  The system will randomly pick a challenge from the list shown on the management page.  If there's only one challenge, then it will always be used.  If you manage to delete all of the challenges, then comment posting will be impossible.  If that happens, you can use the "reset to default" link in the "Challenges" area of the management page to quickly re-enable comments.

You can also set a new master key, or generate a new random one, if you so desire.  There's no particular reason to do this, but what the heck, the option is there.  My favorite manually-set master key is "spammerssuck" but you may feel otherwise.

== Frequently Asked Questions ==

= Do I really need to use this plugin? =

It's proven quite useful in preventing automated comment spam from even reaching the moderation queue, let alone appearing on the site.  It is technically a CAPTCHA, so its presence does mean that any commenter who isn't logged in (and if you don't offer registration, then none of your commenters can be logged in) has to jump through one more hoop in order to comment.  On the other hand, it's completely accessible, unlike image-based CAPTCHA, and you can have a lot of fun with it if you're creative with your challenges.  Some fun and interesting challenges people have used:

* Identify the food in this list: asphalt, bacon, cloud, dagger
* Identify the weapon in this list: asphalt, bacon, cloud, dagger
* How many doors are on a four-door car?
* What is the third word in this sentence?
* What's left if you remove the B from ABC?

= How can I tell if it's working? =

The most obvious way is that a challenge will appear in your comment forms.  The other obvious way is that you'll suddenly stop getting reams of comment spam.

= How do I change the placement of, and markup surrounding, the challenge? =

The plugin adds a function called `gatekeeper_pose_challenge()` which you can use to control where in the comment form the challenge appears.  To use it, you edit your comment form by adding the function call wherever you want the challenge to show up.  You can either pass the markup to be returned as a parameter of `gatekeeper_pose_challenge()`, or you can alter it using the "Markup template" section of the Gatekeeper management page.

= Why is it called 'WP-Gatekeeper' when it's clearly only for WordPress? =

Because there is nothing to prevent an enterprising soul from porting Gatekeeper over to, say, Moveable Type.  In such a case, I'd expect them to call it MT-Gatekeeper.
