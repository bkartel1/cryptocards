import React from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { setCardFilterText, setCardSortOption } from '../actions/cards';
import PropTypes from 'prop-types';
import { CONTRACT_NAME_BATTLEGROUPS } from './../contracts';
import {
  isReadyForContract,
  waitForTxToBeMined,
  getContractInstanceByName
} from '../selectors';
import { Button } from 'reactstrap';
class BattleGroupCreator extends React.Component {
  doContract = async (senderAddress, ci, cardIds) => {
    //todo: web3 check
    let contractInstance = await ci;
    contractInstance
      .createBattleGroup(senderAddress, cardIds, {
        from: senderAddress
      })
      .then(txHash => {
        waitForTxToBeMined(txHash);
      })
      .catch(console.error);
  };

  render() {
    let { ready, contractInstance, cardIds } = this.props;
    let isValidSize = [1, 3, 5].includes(cardIds.length);
    let truncated = cardIds.slice(0, 5);
    while (truncated.length !== 5) {
      truncated.push(0);
    }

    return (
      <div>
        <pre>selected cards: {JSON.stringify({ truncated, cardIds })}</pre>
        <Button
          disabled={!ready || !isValidSize}
          onClick={() =>
            this.doContract(
              this.props.user.main_address,
              contractInstance,
              truncated
            )
          }
        >
          {ready ? 'create battlegroup' : 'loading...'}
        </Button>
      </div>
    );
  }
}

BattleGroupCreator.propTypes = {
  carddIds: PropTypes.array.isRequired
};

function mapStateToProps(state) {
  let { card, user } = state;
  return {
    card,
    user,
    ready: isReadyForContract(state),
    contractInstance: getContractInstanceByName(
      state,
      CONTRACT_NAME_BATTLEGROUPS
    )
  };
}

const mapDispatchToProps = dispatch => {
  return bindActionCreators(
    {
      setCardFilterText,
      setCardSortOption
    },
    dispatch
  );
};
export default connect(mapStateToProps, mapDispatchToProps)(BattleGroupCreator);
