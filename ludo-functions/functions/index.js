const { onValueWritten } = require("firebase-functions/v2/database");
const { initializeApp }  = require("firebase-admin/app");
const { getDatabase }    = require("firebase-admin/database");
const axios              = require("axios");
const { defineString }   = require("firebase-functions/params");

initializeApp();

const LARAVEL_URL  = defineString("LARAVEL_URL");
const APP_SECRET   = defineString("APP_SECRET");

function rollDice() {
  return Math.floor(Math.random() * 6) + 1;
}

// -------------------------------------------------------
// Dice roll request
// -------------------------------------------------------
exports.handleDiceRollRequest = onValueWritten(
  {
    ref:    "/games/{roomId}/state/roll_request",
    region: "europe-west1",
  },
  async (event) => {
    const roomId = event.params.roomId;
    if (!event.data.after.val()) return null;

    const db       = getDatabase();
    const stateRef = db.ref(`games/${roomId}/state`);
    const stateSnap = await stateRef.once("value");
    const state     = stateSnap.val();

    if (!state) return null;

    await stateRef.update({ roll_request: false });

    const roll       = rollDice();
    const rollHistory = state.roll_history || [];
    const newHistory  = [...rollHistory, roll];
    const isTripleSix = newHistory.length >= 3 &&
      newHistory.slice(-3).every((r) => r === 6);

    if (isTripleSix) {
      const playersSnap = await db
        .ref(`games/${roomId}/players`)
        .once("value");
      const players    = playersSnap.val() || {};
      const playerKeys = Object.keys(players);
      const currentIndex = playerKeys.indexOf(state.current_turn);
      const nextIndex    = (currentIndex + 1) % playerKeys.length;

      await stateRef.update({
        dice_roll:    roll,
        phase:        "rolling",
        current_turn: playerKeys[nextIndex],
        roll_history: [],
      });
      return null;
    }

    await stateRef.update({
      dice_roll:    roll,
      phase:        "moving",
      roll_history: newHistory,
    });

    return null;
  }
);

// -------------------------------------------------------
// Game finished — report to Laravel
// -------------------------------------------------------
exports.handleGameFinished = onValueWritten(
  {
    ref:    "/games/{roomId}/state/winner",
    region: "europe-west1",
  },
  async (event) => {
    const roomId = event.params.roomId;

    if (!event.data.after.val())  return null;
    if (event.data.before.val())  return null;

    const winner = event.data.after.val();
    const userId = parseInt(winner.replace("user_", ""));

    const db       = getDatabase();
    const metaSnap = await db.ref(`games/${roomId}/meta`).once("value");
    const meta     = metaSnap.val();

    if (!meta) {
      console.error("No meta found for room:", roomId);
      return null;
    }

    try {
      await axios.post(
        `${LARAVEL_URL.value()}/api/v1/games/result`,
        {
          game_id:          meta.game_id,
          winner_id:        userId,
          firebase_room_id: roomId,
        },
        {
          headers: {
            "Content-Type": "application/json",
            "X-App-Secret":  APP_SECRET.value(),
            "Accept":        "application/json",
          },
          timeout: 10000,
        }
      );
      console.log(`Game ${meta.game_id} result reported. Winner: ${userId}`);
    } catch (error) {
      console.error("Failed to report game result:", error.message);
      await db.ref(`failed_results/${roomId}`).set({
        game_id:   meta.game_id,
        winner_id: userId,
        error:     error.message,
        timestamp: { ".sv": "timestamp" },
      });
    }

    return null;
  }
);

// -------------------------------------------------------
// Player disconnect — skip turn after timeout
// -------------------------------------------------------
exports.handlePlayerDisconnect = onValueWritten(
  {
    ref:    "/games/{roomId}/players/{userId}/is_connected",
    region: "europe-west1",
  },
  async (event) => {
    const { roomId, userId } = event.params;

    if (event.data.after.val()  !== false) return null;
    if (event.data.before.val() !== true)  return null;

    const TIMEOUT_MS = 60000;
    await new Promise((resolve) => setTimeout(resolve, TIMEOUT_MS));

    const db           = getDatabase();
    const connectedSnap = await db
      .ref(`games/${roomId}/players/${userId}/is_connected`)
      .once("value");

    if (connectedSnap.val() === true) return null;

    const stateSnap = await db
      .ref(`games/${roomId}/state`)
      .once("value");
    const state = stateSnap.val();

    if (!state || state.current_turn !== userId) return null;

    const playersSnap = await db
      .ref(`games/${roomId}/players`)
      .once("value");
    const players    = playersSnap.val() || {};
    const playerKeys = Object.keys(players);
    const currentIndex = playerKeys.indexOf(userId);
    const nextIndex    = (currentIndex + 1) % playerKeys.length;

    await db.ref(`games/${roomId}/state`).update({
      current_turn: playerKeys[nextIndex],
      dice_roll:    null,
      phase:        "rolling",
    });

    console.log(`Turn skipped for disconnected player: ${userId}`);
    return null;
  }
);

// -------------------------------------------------------
// Turn timeout — auto-skip after 30 seconds
// -------------------------------------------------------
exports.handleTurnTimeout = onValueWritten(
  {
    ref:    "/games/{roomId}/state/current_turn",
    region: "europe-west1",
  },
  async (event) => {
    const { roomId } = event.params;
    const newTurn    = event.data.after.val();
    if (!newTurn) return null;

    const TURN_TIMEOUT_MS = 30000;
    await new Promise((resolve) => setTimeout(resolve, TURN_TIMEOUT_MS));

    const db              = getDatabase();
    const currentTurnSnap = await db
      .ref(`games/${roomId}/state/current_turn`)
      .once("value");

    if (currentTurnSnap.val() !== newTurn) return null;

    const phaseSnap = await db
      .ref(`games/${roomId}/state/phase`)
      .once("value");

    if (phaseSnap.val() !== "rolling") return null;

    const playersSnap = await db
      .ref(`games/${roomId}/players`)
      .once("value");
    const players    = playersSnap.val() || {};
    const playerKeys = Object.keys(players);
    const currentIndex = playerKeys.indexOf(newTurn);
    const nextIndex    = (currentIndex + 1) % playerKeys.length;

    await db.ref(`games/${roomId}/state`).update({
      current_turn: playerKeys[nextIndex],
      dice_roll:    null,
      phase:        "rolling",
    });

    console.log(`Turn timeout — skipped to: ${playerKeys[nextIndex]}`);
    return null;
  }
);