import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CHAT_HEADER_GESTURE_THRESHOLD,
    classifyGroupChatHeaderGesture,
} from '../../../resources/js/group-chat-header-controller.js';

test('group chat header gesture threshold ignores jitter and classifies direction', () => {
    assert.equal(CHAT_HEADER_GESTURE_THRESHOLD, 10);
    assert.equal(classifyGroupChatHeaderGesture(4), 'idle');
    assert.equal(classifyGroupChatHeaderGesture(-9), 'idle');
    assert.equal(classifyGroupChatHeaderGesture(10), 'hide');
    assert.equal(classifyGroupChatHeaderGesture(-10), 'show');
    assert.equal(classifyGroupChatHeaderGesture(42), 'hide');
    assert.equal(classifyGroupChatHeaderGesture(-42), 'show');
});
