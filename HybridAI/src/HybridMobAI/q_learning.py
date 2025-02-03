import numpy as np
import pickle
import sys

class QLearningAgent:
    def __init__(self, state_size, action_size, learning_rate=0.1, discount_factor=0.99, exploration_rate=1.0, exploration_decay=0.995):
        self.state_size = state_size
        self.action_size = action_size
        self.learning_rate = learning_rate
        self.discount_factor = discount_factor
        self.exploration_rate = exploration_rate
        self.exploration_decay = exploration_decay
        self.q_table = np.zeros((state_size, action_size))

    def choose_action(self, state):
        if np.random.rand() < self.exploration_rate:
            return np.random.randint(self.action_size)
        return np.argmax(self.q_table[state])

    def learn(self, state, action, reward, next_state):
        best_next_action = np.argmax(self.q_table[next_state])
        td_target = reward + self.discount_factor * self.q_table[next_state][best_next_action]
        td_error = td_target - self.q_table[state][action]
        self.q_table[state][action] += self.learning_rate * td_error
        self.exploration_rate *= self.exploration_decay

    def save_model(self, filepath):
        with open(filepath, 'wb') as file:
            pickle.dump(self.q_table, file)

    def load_model(self, filepath):
        with open(filepath, 'rb') as file:
            self.q_table = pickle.load(file)

if __name__ == "__main__":
    agent = QLearningAgent(state_size=10, action_size=5)
    model_path = sys.argv[2]

    if sys.argv[1] == "choose_action":
        state = int(sys.argv[3])
        agent.load_model(model_path)
        action = agent.choose_action(state)
        print(action)

    elif sys.argv[1] == "learn":
        state = int(sys.argv[3])
        action = int(sys.argv[4])
        reward = float(sys.argv[5])
        next_state = int(sys.argv[6])
        agent.load_model(model_path)
        agent.learn(state, action, reward, next_state)
        agent.save_model(model_path)