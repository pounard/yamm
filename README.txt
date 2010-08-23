$Id: README.txt,v 1.3.2.2 2010/05/12 15:38:42 pounard Exp $
Yamm
====

Important notice
----------------

Before using both client or server, install the uuid php pear library, it handle
UUID generation in a nice and really fast way.

If you don't install this extension, the module will generate random time-based
series of numbers instead of standard V4 UUID.

Note that that it should not alter module behavior, just consider installing
this php extension will force the module to use standardised UUID's.

Behaviors that you should know
------------------------------

At pull time, when updating an existing node, the last known revision will be
updated.

Known bugs
----------

You are going to experience some problems with files, we don't support any file
module right now.

If you use image_attach, it will result in image id pointing to nothing, if you
use filefield, you won't have the attached file copied.

Right now, only the default profile is used to pass data to client at pull time.
