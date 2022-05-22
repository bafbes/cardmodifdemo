# cardmodifdemo
Demo Module for Dolibarr PR #20972 ( Proposition of Object Oriented Scripts Structure applied to propales)

The module overloads the method Actions::propal_card_actions() defined in /comm/propal/card.php to prohibit modifying associated project if the propal 
is not in draft state and with no lines 

