# PHP-IoC

A helper package used by other Secure Trading packages.

Provides an Inversion of Control class `\Securetrading\Ioc\Ioc`.  Instances of this class can map 'aliases' to class names or factory methods; these aliases can then later be used to instantiate objects.

It also provides a helper class `\Securetrading\Ioc\Helper` that applications can use to easily register alias mappings to an instance of `Securetrading\Ioc\Ioc` without the application code needing to explicitly call `Securetrading\Ioc\Ioc::get()`.

## Release History

| Version  | Changes                        |
| -------- |---------------                 |
| 3.0.0    | PHP 8 compatibility.           |
| 2.0.0    | PHP 7.3 and 7.4 compatibility. |
| 1.0.0    | Initial Release                |

## PHP Version Compatibility

| Version  | Changes                        |
| -------- |---------------                 |
| 2.0.0    | PHP 7.3 - PHP 7.4              |
| 1.0.0    | PHP 5.3 - PHP 7.2              |

## \Securetrading\Ioc\Ioc - Usage

Construct instances either like this:

    $ioc = new \Securetrading\Ioc\Ioc();

Or like this:

    $ioc = \Securetrading\Ioc\Ioc::instance();

Register an alias with `set()`:

    $ioc->set('anAlias', '\stdClass');
    
Create instances with `get()`:

    $ioc->set('anAlias', '\stdClass');
    $instance = $ioc->get('anAlias');
    var_dump(get_class($instance)); // "stdClass"
    
An optional array can be passed to `get()`.  The values in this array will be passed to the constructor of the constructed instance:

    class A {
        public function __construct($a, $b) {
            echo "values are " . $a . " and " . $b . PHP_EOL; // Outputs "values are param1 and param2"
        }
    }
    
    $ioc->set('anAlias', '\A');
    $instance = $ioc->get('anAlias', ['param1', 'param2']);

If the alias passed to 'get' has not been registered with a call to `set()` and the alias is actually a valid class name then an instance of that class will be returned:

    class A {}

    var_dump(get_class( $ioc->get('\A') )); // "A"

    $ioc->set('\A', '\stdClass');
    var_dump(get_class( $ioc->get('\A') )); // "stdClass"

Check to see if an alias has been added with `has()`:

    var_dump( $ioc->has('anAlias') ); // false
    $ioc->set('anAlias', '\stdClass');
    var_dump( $ioc->has('anAlias') ); // true
   
`set()` accepts an alias and either a literal class name (as shown earlier) or a factory method.  This factory method will be called when `get()` is called with the alias.

    $ioc->set('anAlias', function(\Securetrading\Ioc\IocInterface $ioc, $alias, $params) {
        // $ioc is the same $ioc instance that 'set' is being called on.
        // $alias is the alias passed when the 'get' method was called.
        // $params come from the second argument to the 'get' method call.
        return new \stdClass();
    });

    $instance = $ioc->get('anAlias', ['optionalAdditionalParam1', 'optionalAdditionalParam2']);
    var_dump(get_class($instance)); // stdClass
      
The `create()` method is an alias for `get()`:

    $ioc->get('anAlias');
    $ioc->create('anAlias'); // Same as above.
    
The `getSingleton()` method resolves an alias to a class instance: multiple calls to `getSingleton()` with an alias will always return the same instance.

    $ioc->set('anAlias', '\stdClass');
    var_dump( $ioc->get('anAlias') === $ioc->get('anAlias') ); // false
    var_dump( $ioc->getSingleton('anAlias') === $ioc->getSingleton('anAlias') ); // true

The `before()` method can be used to register a function that will be called before an instance is constructed from an alias:

    $ioc->set('anAlias', '\stdClass');
    $ioc->set('anotherAlias', '\stdClass');
    
    $ioc->before('anAlias', function($alias, array $params = array()) {
        echo "in before callback for alias '" . $alias . "'" . PHP_EOL;
    });
    
    $ioc->get('anAlias'); // Will trigger the 'before' function
    $ioc->get('anotherAlias'); // Will not trigger the 'before' function.
    
The wildcard `*` can be used to register a before callback that will be triggered before each instance is constructed, regardless of the alias:

    $ioc->set('anAlias', '\stdClass');
    $ioc->set('anotherAlias', '\stdClass');
    
    $ioc->before('*', function($alias, array $params = array()) {
        echo "in before callback for alias '" . $alias . "'" . PHP_EOL;
    });
    
    $ioc->get('anAlias'); // Will trigger the 'before' function
    $ioc->get('anotherAlias'); // Will trigger the 'before' function.
    
An `after()` instance method can also be called.  This works just like the `before()` instance method and also accepts the wildcard `*`:

    $ioc->set('anAlias', '\stdClass');
    $ioc->set('anotherAlias', '\stdClass');
    
    $ioc->after('*', function(\Securetrading\Ioc\IocInterface $ioc, $constructedInstance, $alias, array $params = array()) {
         echo "in after callback for alias '" . $alias . "'" . PHP_EOL;
    });

    $ioc->get('anAlias'); // Will trigger the 'after' function
    $ioc->get('anotherAlias'); // Will trigger the 'after' function.
    
Helper methods for managing config options are provided.  These may be useful in e.g. the factory methods given as the second parameter to `set()` or in the callbacks given to the `before()` and `after()` methods:

    $ioc->setOption('our_option', 'our_value');
    var_dump( $ioc->hasOption('our_option') ); // true
    var_dump( $ioc->hasOption('our_other_option') ); // false
    var_dump( $ioc->getOption('our_option') ); // 'our_value'
    $ioc->getOption('our_other_option'); // \Securetrading\Ioc\IocException thrown with code CODE_OPTION_MISSING.

Helper methods for checking the existence of parameters in an array and for retrieving values from an array (or a default value if they do not exist) have also been provided.  These are given to make working with the `$params` given as the optional second param to `get()` in the factory methods easier:

    var_dump( $ioc->hasParameter('key', []) ); // false
    var_dump( $ioc->hasParameter('key', ['key' => 'value']) ); // true
    var_dump( $ioc->getParameter('key', [], 'default_value') ); // 'default_value'
    var_dump( $ioc->getParameter('key', ['key' => 'value']) ); // 'value'
    $ioc->getParameter('key', []); // throws \Securetrading\Ioc\IocException with code CODE_PARAM_MISSING.
    
## \Securetrading\Ioc\Helper - Usage

Use of the `Helper` is optional.

The `Helper` can - by parsing special 'helper files' - make implicit repeated calls to `\Securetrading\Ioc\Ioc::set()` so applications don't always need to explicitly define lots of alias mappings.

It can be constructed in any of these ways:

    $helper = new \Securetrading\Ioc\Helper(); // A
    $helper = \Securetrading\Ioc\Helper::instance(); // B

    $ioc = new \Securetrading\Ioc\Ioc();

    $helper = new \Securetrading\Ioc\Helper($ioc); // C
    $helper = \Securetrading\Ioc\Helper::instance($ioc); //D

`A` and `B` are effectively the same; so are `C` and `D`.   If `A` or `B` is used then the constructor will automatically create an instance of `\Securetrading\Ioc\Ioc` and assign it to the Helper; if `C` or `D` are used then the given instance of `\Securetrading\Ioc\IocInterface` is passed to the `\Securetrading\Ioc\Helper` constructor so it does not automatically create another one.

The `Helper` firstly needs to find valid 'helper files'.  Helper files must be named `*_ioc.php`, where `*` is any character valid in a filename.   The user must tell the Helper where to find helper files by calling one or more of these methods: `addEtcDirs()` and `addVendorDirs()`.

`addEtcDirs()` tells the `Helper` to look inside the dir given in the argument for a helper file:

    $helper->addEtcDirs('/path/to/an/etc/dir/');

`addEtcDirs()` is overloaded so it can also be passed an array of helper files:

    $helper->addEtcDirs(['/path/to/an/etc/dir/', '/path/to/another/etc/dir']);
    
`addVendorDirs()` is designed for use with a Composer-based application and should point to a `vendor` directory created by Composer.  The `Helper` will then look inside each vendor name and package name for an `etc` dir.  Each valid helper file from this `etc` dir will then be loaded.

    $helper->addVendorDir('/path/to/a/composer/based/application/vendor/'); // E.g. a valid helper file might be '/path/to/a/composer/based/application/vendor/vendorName/applicationName/etc/our_ioc.php'
    
`addVendorDirs()` - like `addEtcDirs()` - is overloaded so more than once vendor dir can be specified at a time:

    $helper->addVendorDir(['/path/to/a/composer/based/application/vendor/', '/path/to/another/composer/based/application/vendor/']);

Once the location of valid helper files has been registered with `addEtcDirs()` or `addVendorDirs()` (see above) then `loadPackage()` or '`loadPackages()` can be called.  These functions do the following:

1. Parse all loaded helper files.
2. Build an array of 'packages' defined by the helper files.
3. Loads the package(s) requested by the call to `loadPackage()` or `loadPackages()` by reading the alias definitions in the helper files and setting them to the IoC instance by calling `\Securetrading\Ioc\Ioc::get()`.
4. Loads any dependent packages, repeating the above step on them.  This allows e.g. package `A` to auatomatically load the definitions for package `B` and `C` without client code explicitly requesting to load package `B` and `C` in the call to `loadPackage()`.  This is useful for e.g. imitating Composer dependencies at the IoC level.

Helper files look like this:

    return [
      'aPackageNameHere' => [
        'definitions' => array(
              'anAlias' => '\stdClass',
          'anotherAlias' => ['\aClass', 'aMethodInTheClass'], // A factory method.
        ],
        'dependencies' => [
          'anotherPackageNameHere', // This means that the 'definitions' from the 'anotherPackageNameHere' will also be loaded and set to the IoC container.
        ],
      ],
    ];

A call to `loadPackage()` looks like this:

    $helper->loadPackage('packageName');
    
Multiple packages can also be loaded by calling `loadPackages()`:

    $helper->loadPackages(['packageName', 'anotherPackageName']);
    
After `loadPackage()` or `loadPackages()` have been called then the IoC container can be returned:

    $ioc = $helper->getIoc();

Other methods (mostly useful for debugging) have also been provided for examining the helper files found and the loaded packages:

    var_dump( $helper->getPackageDefinitionFiles() );
    var_dump( $helper->getPackageDefinitions() );
    var_dump( $helper->getLoadedPackageNames() );
    
For reference - typical usage of the `Helper` might look like this:

    $ioc = \Securetrading\Ioc\Helper::instance()
      ->addVendorDirs(__DIR__ . '/vendor'/)
      ->loadPackage('ourPackageName')
      ->getIoc();
      
    $instance = $ioc->get('alias');  // Using the IoC container.  Note we did not need to explicitly register 'alias' with the container.
    
