// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract CivicLedger {
    event CommitmentRecorded(bytes32 indexed recordHash, uint256 timestamp);

    mapping(bytes32 => uint256) public commitments;

    function recordCommitment(bytes32 recordHash) external {
        require(commitments[recordHash] == 0, "Record already exists");
        commitments[recordHash] = block.timestamp;
        emit CommitmentRecorded(recordHash, block.timestamp);
    }

    function verifyCommitment(bytes32 recordHash) external view returns (bool, uint256) {
        uint256 timestamp = commitments[recordHash];
        return (timestamp > 0, timestamp);
    }
}
