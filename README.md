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

The used property can be changed in the settings, if you like to use a different name:

    Flownative:
      Neos:
        CustomDocumentUriRouting:
          uriPathPropertyName: 'myCustomUriPathProperty'

### Excluding paths from matching

In order to exclude specific request paths (e.g. public resources), the `matchExcludePatterns`
setting exists. All given array values will skip the matching process for request paths that start
with the value. The default shipped with the package is:

    Neos:
      CustomDocumentUriRouting:
        matchExcludePatterns:
          - '_Resources'

Any URI starting with `_Resources` will be ignored by the package and passed through.

## Credits

Development of this package has been sponsored by web&co OG, Vienna, Austria.
