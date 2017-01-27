# Knorke

## class StatisticValue

It allows you to define some kind of a mapping and to compute values which depend one others. That is helpful in cases where you have a handful of values, you define, but some which are depend on these values and need to be computed. Such computational rules are usually domain related and should not be put into code. Using this class, you can compute the depending values very easily only providing rules and the non-depending values.

### Example

**Non-defined values**:
* value1 = 3

**Computational rules**:
* value2 = value1 + 5
* value3 = (value1 * 3)/2

**value1** is given and set by the user. **value2** and **value3** will be computed.

These rules are to described in **RDF** as following:
```
@prefix : <http://foo/> .

:value1 rdf:type kno:StatisticValue .
    
:value2 rdf:type kno:StatisticValue ;
    kno:computationOrder [
        kno:_0 "[:value1]+5"
    ];
    
:value3 rdf:type kno:StatisticValue ;
    kno:computationOrder [
        kno:_0 "[:value1]*3" ;
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

$statisticValueInstance = new StatisticValue($store, new CommonNamespaces(), $mapping);

$computedValues = $statisticValueInstance->compute();
```

Variable **$computedValues** will look like this now:

```php
$result = array(
   'http://foo/value1' => 3,
   'http://foo/value2' => 7,   // rule was: value1 + 5
   'http://foo/value3' => 4.5  // rule was: (value1 * 3)/2
);
```

### Conclusion

In case you want to compute values which depend on each other, use StatisticValue class. Define your non-depended values and let the class handle the rest. It allows you to keep your code clean from domain specific computation. Furthermore, it allows you to let the rules be handled by domain experts while you only need to integrate them. Because its very straight forward, you dont have to care about which is computed how.
