import { PlayerData } from '../types';

export default function player(state = null, action): PlayerState|null {
    return state;
};

export interface PlayerState {
    name: string|null,
    data: PlayerData|null,
}
