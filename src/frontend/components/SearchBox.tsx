import { FormEvent, useState } from 'react';
import { connect } from 'react-redux';
import { setPlayers } from '../actions';
import { AppDispatch, AppState } from '../store';

const SearchBox = ({ setPlayers }) => {
    const [player1, setPlayer1] = useState('');
    const [player2, setPlayer2] = useState('');

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        setPlayers(player1 || null, player2 || null);
    }

    return (
        <form onSubmit={handleSubmit}>
            <input type="text" name="player1" value={player1} onChange={(event) => setPlayer1(event.currentTarget.value)}  />
            <input type="text" name="player2" value={player2} onChange={(event) => setPlayer2(event.currentTarget.value)} />
            <button type="submit">Lookup</button>
        </form>
    );
}

const mapStateToProps = (state: AppState) => ({
    playerName: state.player1,
});

const mapDispatchToProps = (dispatch: AppDispatch) => ({
    setPlayers: (playerName1: string|null, playerName2: string|null) => dispatch(setPlayers(playerName1, playerName2)),
});

export default connect(mapStateToProps, mapDispatchToProps)(SearchBox);
