import { AppDispatch, AppState } from '../store';
import { PlayerData } from '../types';

export const SET_LOADING = 'SET_LOADING';
export const SET_PLAYER_NAMES = 'SET_PLAYER_NAMES';
export const SET_PLAYER_DATA = 'SET_PLAYER_DATA';

const samplePlayerData = {
    name: 'Villermen',
    skills: [],
    activities: [],
    log: [],
};

// TODO: Make this into a non-redux method that just triggers all the redux actions?
export function setPlayers(player1Name: string|null, player2Name: string|null) {
    return async (dispatch: AppDispatch, state: AppState) => {
        dispatch({
            type: SET_PLAYER_NAMES,
            payload: [player1Name, player2Name],
        }); // TODO: Triggers URL change via react-router (useHistory())
        dispatch({
            type: SET_LOADING,
            payload: true,
        });

        const fetchPlayerData = async (name: string|null): Promise<PlayerData|null> => {
            return name ? samplePlayerData : null;
        };

        const playerData = await Promise.all([
            fetchPlayerData(player1Name),
            fetchPlayerData(player2Name)
        ]);

        dispatch({
            type: SET_PLAYER_DATA,
            payload: playerData,
        })
    };
}
