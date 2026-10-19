# Development

## Building the JavaScript bundle

The source is in `client/src/javascript/`, the build in `client/dist/javascript/`. The build is committed, so projects using the module don't need Node.

```shell
npm install
npm run build   # production build
npm run dev     # development build, rebuilds on change
```

Commit the updated `client/dist/` together with your source changes.

## Releases

Versions follow [Semantic Versioning](https://semver.org) with tags in the form `1.2.0`. The changes per version are in the GitHub [Releases](../../releases), generated from the merged pull requests. There is no separate changelog file to maintain.

## Screenshots

Images for the documentation are in `docs/en/images/`. They are excluded from the Composer package.
