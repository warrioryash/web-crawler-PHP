/* * Create a procedure to keep track of the seen and  */
/* *        unseen links while crawling a domain       */
/* *                                                   */
/* * @author Yash Singh <warrioryash@protonmail.com>   */
/* * @version 0.1                                      */
/* * @since 0.1                                        */
/* * @access public                                    */
/* *                                                   */
DELIMITER //                                           
  
CREATE PROCEDURE `keywordsURLs` (IN theKey VARCHAR(255), IN theURL VARCHAR(255))  

BEGIN 

/* Declare variables with default value of 0 */
DECLARE theKey_id, theURL_id INT DEFAULT 0; 

SELECT keyword_id INTO theKey_id FROM keywords WHERE keyword=theKey;
SELECT URL_id INTO theURL_id FROM URLs WHERE URL=theURL;

/* Check if incoming keyword has already been seen before */
IF (theKey_id >0) THEN

   /* The incoming keyword has been seen. */
   /* If the incoming URL has been seen too -- do NOTHING */
   /* If it has not been seen then add the page (URL) */
   IF (theURL_id = 0) THEN
      INSERT INTO URLs VALUES ('', theURL);
      SET theURL_id = LAST_INSERT_ID(); 
      /* Now add the keyword and URL to the keywords_URLs table  */
      INSERT INTO keywords_URLs VALUES (theKey_id,theURL_id);
   END IF;

/* Incoming keyword has NOT been seen before -- ADD IT */
ELSE
   INSERT INTO keywords VALUES ('', theKey);
   SET theKey_id = LAST_INSERT_ID(); 

   /* Check if corresponding URL has been seen*/
   IF (theURL_id >0) THEN
      /* It has been seen. No need to add it to the URLs table*/
      /* Now add the keyword and URL to the keywords_URLs table  */
      INSERT INTO keywords_URLs VALUES (theKey_id,theURL_id);   
   ELSE
      INSERT INTO URLs VALUES ('', theURL);
      SET theURL_id = LAST_INSERT_ID(); 
      /* Now add the keyword and URL to the keywords_URLs table  */
      INSERT INTO keywords_URLs VALUES (theKey_id,theURL_id);  

   END IF;

END IF;
END//
