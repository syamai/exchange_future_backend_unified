#!/bin/bash
export LOG4J_LEVEL=DEBUG
COMMAND="$*"
if [[ -n "$COMMAND" ]]
then
    java -Xmx6144M -cp MatchingEngine-1.0-shaded.jar com.sotatek.future.TickerEngineCLI $KAFKA_BROKERS
else
    java -jar MatchingEngine-1.0-shaded.jar $KAFKA_BROKERS
fi