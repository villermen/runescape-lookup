import { combineReducers } from 'redux';
import player from './player';

export default combineReducers({
    player1: player,
    player2: player,
});
