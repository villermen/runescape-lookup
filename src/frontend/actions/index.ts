import { AppDispatch, AppState } from '../store';

export function setPlayers(playerName1: string|null, playerName2: string|null) {
    return (dispatch: AppDispatch, state: AppState) => {
        console.log('setPlayers');
    };
}
