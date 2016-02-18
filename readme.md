Work in progress.

This is a streaming pull parser - like [XMLReader](http://php.net/xmlreader), but for JSON.

Assumes UTF-8 encoded JSON, though does not strictly enforce it.

Needs tests.

Objects will be returned as arrays with strings for keys. Trying to return stdClass objects gains us nothing but
exposure to edge cases where valid JSON produces property names that are not allowed in PHP objects (e.g. "", "\u000")
The behavior of `json_decode()` in these cases is inconsistent and can introduce key collisions, so we'll not be
following its lead.

## Example ##
```php
use pcrov\JsonReader\JsonReader;
```
