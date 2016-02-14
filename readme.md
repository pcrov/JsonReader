Work in progress.

This is a streaming pull parser - like [XMLReader](http://php.net/xmlreader), but for JSON. It even keeps some relevant bits of the interface the same.

Only supports UTF-8 encoded JSON.

Needs a butt-load of tests.

Objects will be returned as associative arrays. This is to avoid dealing with a couple edge cases:

 1. Property names consisting of empty strings. Allowed in JSON, not in PHP.
 
    Alternatives: handle them in the same manner as [`json_decode()`](http://php.net/json_decode) which replaces them with `_empty_` (which is a little weird and [can cause key collisions](https://3v4l.org/LUFUK).)
 2. Dirty, dirty objects. Because we cache parsed nodes it's possible for a given object to be returned multiple times - any changes made to that object would be reflected in all other returns. This is not ideal.
 
    Alternatives: instead cache the string until the cursor moves beyond that subtree, reparsing as we go; or when returning a compound type hit it with something like `unserialize(serialize($node))` to force a deep clone of all things therein. Neither performance hit seems worth it just so that objects can be returned safely.

I'm not terribly keen on those alternatives, and we lose nothing I'm aware of by returning associative arrays instead, but I'm open to suggestions.