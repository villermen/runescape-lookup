import { Provider } from 'react-redux';
import App from './components/App';
import { render } from 'react-dom';
import { store } from './store';

render((
    <Provider store={store}>
        <App />
    </Provider>
), document.getElementById('reactRoot'));
