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

Dependencies
------------
Numerous dependencies are optional, but can give you great things, you should
consider install:

 - PHP-UUID pear extention, that will generate V4 UUID for entities.

 - MimeDetect Drupal module, that will allow some finer mime type detection
   for file. If you are using the FileField module, Yamm will override this
   module's mime detection using its own.

Known bugs
----------
You are going to experience some problems with files, we don't support any file
module right now.

If you use image_attach, it will result in image id pointing to nothing, the
only provided support is for the filefield module, and it suffers from lack
of testing.

Right now, only the default profile is used to pass data to client at pull time,
we are going to work on enabling different profile at the same time, for each
client.
