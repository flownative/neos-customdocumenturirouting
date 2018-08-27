[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Packagist](https://img.shields.io/packagist/v/flownative/neos-customdocumenturirouting.svg)](https://packagist.org/packages/flownative/neos-customdocumenturirouting)
[![Maintenance level: Fiendship](https://img.shields.io/badge/maintenance-%E2%99%A1%E2%99%A1-ff69b4.svg)](https://www.flownative.com/en/products/open-source.html)

# Custom Document URI paths for Neos

This allows to have custom document URI paths for document nodes. This means, independent from the
URI path that is usually built from the uriPathSegments of each node, a document can be given a
full, unique, custom URI path.

## Installation

`composer require flownative/neos-customdocumenturirouting`

## Configuration

After installing the package, by default it looks for a property called `uriPath` in document nodes.

The `uriPath` property can be added to your document nodes by using the provided
`Flownative.Neos.CustomDocumentUriRouting:UriPathMixin`. 

The used mixin node type name and property name can be changed in the settings, if you like to use a different name:

    Flownative:
      Neos:
        CustomDocumentUriRouting:
          mixinNodeTypeName: 'Acme.Product:UriPathMixin'
          uriPathPropertyName: 'myCustomUriPathProperty'

Make sure that, if you configure a custom mixin node type name, that node type actually provides a property with the
name you defined in `uriPathPropertyName`.

### Excluding paths from matching

In order to exclude specific request paths (e.g. public resources), the `matchExcludePatterns`
setting exists. All given array values will skip the matching process for request paths that start
with the value. The default shipped with the package is:

    Neos:
      CustomDocumentUriRouting:
        matchExcludePatterns:
          - '_Resources'

Any URI starting with `_Resources` will be ignored by the package and passed through.

### A note about performance

This package provides a custom node route part handler which will check if the current HTTP request
matches a given uri path. The route part handler uses a Flow Query to do that. If the Flow Query finds too
many nodes, because the criteria is too broad, frontend and backend performance can suffer, especially
if your content repository contains thousands of nodes.

Therefore try to limit possible matches to a minimum: only use the configured mixin in those node types
which actually need them. For example, if you have a custom "Landing Page" node type, you may want to
define the `CustomDocumentUriRoutingMixin` as a super type. But what you won't do is assign that mixin
to the `Neos.Neos:Document` node type - because that would match all possible document nodes in the system.

## Credits

Development of this package has been sponsored by web&co OG, Vienna, Austria.
