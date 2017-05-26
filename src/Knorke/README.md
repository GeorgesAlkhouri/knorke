# Knorke

## class DataValidator

This class makes data validation a breeze. The main approach is to focus on properties and build very flat and basic structures. Even for bigger knowledge bases this should lead to good understandable structures.

### Example

Take the following RDF snippet. It describes constrains of the class `foaf:Person`.

```ttl
foaf:Person kno:has-property http://age ;
    kno:has-property http://name .

http://age kno:restriction-has-datatype "number" .
```

As you can see, `foaf:Person` has 2 properties: `http://age` and `http://name`, whereas `http://age` got restricted to be of datatype `number`.

Load this into your store and you can validate data as in the following:

```php
$store = /* fill your store with RDF like mentioned */;

$dataValidator = new DataValidator(/* ... */);

// here validate will return true
$dataValidator->validate(array(
    'rdf:type' => 'foaf:Person',
    'http://age' => 42,
    'http://name' => 'Helmut',
));

// here validate will return false,
// because http://age is not of type number
$dataValidator->validate(array(
    'rdf:type' => 'foaf:Person',
    'http://age' => 'not a number', // <=== problematic!
    'http://name' => 'Helmut',
));
```

### Sub structures are possible too

You need sub structure, if you want to model restrictions which affect multiple instances.

For instance, a `http://user` has settings. We also force `http://important-setting` to be set as part of `http://Settings`. You can model it like this:

```
http://user kno:has-property http://has-settings .

http://Settings a owl:Class ;
    kno:has-property http://important-setting .
```

You can use these RDF with the following code:

```php
$store = /* fill your store with RDF like mentioned */;

$dataValidator = new DataValidator(/* ... */);

// here validate will return true
$dataValidator->validate(array(
    'rdf:type' => 'http://user',         // <== main structure
    'http://has-settings' => array(
        'rdf:type' => 'http://Settings', // <== sub structure
        'http://important-setting' => 'true'
    )
));

// here validate will return false,
// because of missing http://important-setting
$dataValidator->validate(array(
    'rdf:type' => 'http://user',         // <== main structure
    'http://has-settings' => array(
        'rdf:type' => 'http://Settings', // <== sub structure
        // missing 'http://important-setting'
    )
));
```


Sure, you didn't modeled the constraint explicit, for instance, the `http://has-settings` property my point to a a resource of a different type. But in my opinion, if you control the data handling, such cases are rare and may be detected by a sufficient test environment. 


## class StatisticValue

It allows you to define some kind of a mapping and to compute values which depend one others. That is helpful in cases where you have a handful of values, you define, but some which are depend on these values and need to be computed. Such computational rules are usually domain related and should not be put into code. Using this class, you can compute the depending values very easily only providing rules and the non-depending values.

### Example

**Non-defined values**:
* foo:value1 = 3

**Computational rules**:
* foo:value2 = foo:value1 + 5
* foo:value3 = (foo:value1 * 3)/2

**foo:value1** is given and set by the user. **foo:value2** and **foo:value3** will be computed.

These rules are to described in **RDF** as following:
```
@prefix foo: <http://foo/> .

foo:value1 rdf:type kno:StatisticValue .

foo:value2 rdf:type kno:StatisticValue ;
    kno:computation-order [
        kno:_0 "[foo:value1]+5"
    ];

foo:value3 rdf:type kno:StatisticValue ;
    kno:computation-order [
        kno:_0 "[foo:value1]*3" ;
        kno:_1 "/2"
    ];
```

The RDF describes *all* values relevant for computation. Now we look into PHP-code:
```php

$store = /* load RDF string into a Store instance, i.e.g InMemoryStore */;

// define your non-depending values. these are usually ones which you provide in
// order to compute all depending ones.
$mapping = array(
   'http://foo/value1' => 3
);

// add foo namespace to collection for later use
$namespaces = new CommonNamespaces();
$namespaces->add('foo', 'http:/foo/');

$statisticValueInstance = new StatisticValue($store, $namespaces, $mapping);

$computedValues = $statisticValueInstance->compute();
```

Variable **$computedValues** will look like this now:

```php
$result = array(
   'http://foo/value1' => 3,
   'http://foo/value2' => 8,   // rule was: value1 + 5
   'http://foo/value3' => 4.5  // rule was: (value1 * 3)/2
);
```

### Conclusion

In case you want to compute values which depend on each other, use StatisticValue class. Define your non-depended values and let the class handle the rest. It allows you to keep your code clean from domain specific computation. Furthermore, it allows you to let the rules be handled by domain experts while you only need to integrate them. Because its very straight forward, you dont have to care about which is computed how.
