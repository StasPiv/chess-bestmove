FROM ubuntu
RUN apt-get update
ENV TZ=Europe/Kiev
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN apt-get install php php-cli php-json php-curl php-xml php-pear -y -q
RUN apt-get install wget
RUN wget https://get.symfony.com/cli/installer -O - | bash
RUN mv /root/.symfony/bin/symfony /usr/local/bin/symfony
RUN apt-get install git -y
RUN git clone https://github.com/StasPiv/chess-bestmove.git /root/chess-bestmove
RUN apt-get install composer -y
RUN apt-get install polyglot -y
RUN apt-get install stockfish -y
WORKDIR /root/chess-bestmove
RUN composer install
COPY ws-listener.php /root/chess-bestmove/ws-listener.php
COPY read-engine.php /root/chess-bestmove/read-engine.php
RUN chmod +x /root/chess-bestmove/ws-listener.php
RUN chmod +x /root/chess-bestmove/read-engine.php
ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/snap/bin
RUN touch engine.log