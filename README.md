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

## Credits

Development of this package has been sponsored by web&co OG, Vienna, Austria.
