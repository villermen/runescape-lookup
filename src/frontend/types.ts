export interface PlayerData {
    name: string,
    skills: HighscoreSkill[],
    activities: HighscoreActivity[],
    log: LogItem[],
    // tracked: boolean;
}

export interface HighscoreSkill {
    id: number;
    name: string;
    xp: number;
    rank: number;
    level: number;
    virtualLevel: number;
    xpToNextLevel: number;
    progressToNextLevel: number;
    // highscore:
    // highscoreDate:
}

export interface HighscoreActivity {
    id: number;
    name: string;
    score: number;
    rank: number;
}

export interface LogItem {
    time: string;
    title: string;
    description: string;
}
