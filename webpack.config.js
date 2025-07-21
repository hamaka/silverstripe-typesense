const path = require('path');
const webpack = require('webpack');
const glob = require('glob');

module.exports = {
  entry: {
    bundle: './' + glob.sync('client/src/javascript/**/*.js')
  },
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'client', 'dist', 'javascript'),
    library: {
      name: 'TypesenseInstantSearch',
      type: 'umd',
      export: 'default'
    },
    globalObject: 'this'
  },
  externals: {
    jquery: 'jQuery'
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        include: path.resolve(__dirname, 'client/src/javascript')
      }
    ]
  },
  plugins: [
    new webpack.DefinePlugin({
      'process.env.SS_ENVIRONMENT_TYPE': JSON.stringify(process.env.SS_ENVIRONMENT_TYPE)
    })
  ]
};
