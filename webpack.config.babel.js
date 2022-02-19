import path from 'path';
import HtmlWebpackPlugin from 'html-webpack-plugin';

const babelLoaderOptions = {
    cacheDirectory: true,
};

export default {
    entry: {
        main: path.resolve(__dirname, 'src/frontend/index.tsx'),
    },
    output: {
        filename: '[name].js?[contenthash:8]',
        chunkFilename: '[name].js?[contenthash:8]',
        path: path.resolve(__dirname, 'public'),
        // publicPath: '/rslookup/', TODO: Load from .env
    },
    module: {
        rules: [
            {
                test: /\.(js|jsx|ts|tsx)$/,
                exclude: /node_modules/,
                loader: 'babel-loader',
                options: babelLoaderOptions,
            },
            {
                test: /\.css$/,
                use: [
                    { loader: 'style-loader' },
                    { loader: 'css-loader' },
                ],
            },
            {
                test: /\.(jpg|png)$/,
                include: path.resolve(__dirname, 'assets'),
                loader: 'file-loader',
                options: {
                    // Removes the leading /assets/. Could be done using the
                    // regExp option but it does not convert Windows'
                    // backslashes for some reason
                    name: (path) => path
                        .substr(__dirname.length + '/assets/'.length)
                        .replace(/\\/g, '/')
                        + '?[hash:8]',
                },
            },
        ],
    },
    resolve: {
        modules: [
            path.resolve(__dirname, 'src/frontend'),
            path.resolve(__dirname, 'node_modules'),
        ],
        alias: {
            assets: path.resolve(__dirname, 'assets'),
        },
        extensions: ['.js', '.jsx', '.ts', '.tsx'],
    },
    plugins: [
        new HtmlWebpackPlugin({
            filename: 'index.html',
            template: 'assets/index.html',
            xhtml: true,
        }),
    ],
    devServer: {
        historyApiFallback: true,
    },
};