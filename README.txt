AutoCodeLoader

This project will automatically generate standardised factories, proxies and
setter traits, to avoid you having to duplicate this boilerplate constantly.
It also provides a reliable means for cache-busting autogenerated files and
the ability to pre-generate all files.

AutoCodeLoader will automatically generate the following kinds of files:

- Factories - \MyNamespace\MyPackage\MyClassFactory
- Proxies - \MyNamespace\MyPackage\MyClassProxy, \MyNamespace\MyPackage\MyInterfaceProxy
- Shared Proxies - \MyNamespace\MyPackage\MyClassSharedProxy, \MyNamespace\MyPackage\MyInterfaceSharedProxy
- Setter Traits - \MyNamespace\MyPackage\NeedsMyClassFactoryTrait

Some notes:

- Shared proxies will retrieve services from the dependency injection
  container, meaning all usages of the same shared proxy will deal with the
  same instance of the requested service.
- It is possible to autogenerate setter traits for autogenerated factories and
  proxies, as the above example implies.

To use this in production systems, you're going to want to pre-generate all
files. Pre-generating all files gives you the ability to exclude this package
entirely from your deployments, giving you the peace of mind that no
autogeneration is occurring in production systems. To be able to use the files
generated by this package when it isn't present, you simply need to bootstrap
the composer autoloader like so:

  $composer = require("/path/to/vendor/autoload.php");
  if (class_exists("\\MattyG\\AutoCodeLoader\\Autoloader") === true) {
      \MattyG\AutoCodeLoader\Autoloader::registerAutoloader("/path/to/generated/files");
  } else {
      $composer->addPsr4("", "/path/to/generated/files");
  }

This package makes quite a few assumptions at the moment:

- It will only generate proxies and factories designed to work with the Aura.Di
  dependency injection container. Support for other dependency injection
  containers is most welcome, but I do not have time to build this support at
  the moment.
- There is no magic with the setter traits - it relies on you using them,
  and/or configuring them for use within your dependency injection container.

In conclusion:

I built this because I was unable to find something simple that wasn't weighed
down by many dependencies, and that had first-class support for PHP7. Nothing
else was as simple as what I needed and was able to work with the Aura.Di
container, which is my personal preference.

You should not depend on this library if you are writing a library yourself!
That would be like depending on Illuminate\Support, which is a bad idea. This
library is designed solely for use within applications where a large amount
of code is being written. Do not burden your users with unnecessary
dependencies!

This software is released into the public domain without any warranty.
